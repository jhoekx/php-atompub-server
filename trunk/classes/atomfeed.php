<?php

require_once("appfilestore.php");
require_once("appentry.php");

require_once("appevents.php");
require_once("appuritemplate.php");

require_once("httpresponse.php");
require_once("httpexception.php");

require_once("feedserializer.php");

class Atom_Feed extends EventHTTPResource {
	
	public $base_uri;
	public $store;
	
	public $name;
	public $pagenr;
	
	public $feed_uri;
	
	protected $service;
	
	public $page_length = 10;
	
	public function __construct($uri, $store, $service) {
		$this->base_uri = $service->base_uri;

		if ( is_string($uri) ) { // name
			$name = $uri;
			$nuri = new URI($name."/");
			$uri = $nuri->resolve($service->base_uri);
		}
		
		parent::__construct($uri);
		
		$this->set_name_and_pagenr();
		$this->feed_uri = $this->base_uri.$this->name."/";
		
		$this->store = $store;
		$this->service = $service;
		
	}
	
	public function http_GET($request) {
		$response = new HTTPResponse();
		
		// Caching
		$time = $this->last_modified();
		
		$etag = '"'.md5($time.json_encode($this->get_collection_list())).'"';
		$last_modified = $this->time_to_gmt($time);
		
		if ( $this->try_cache($request, $response, 
			array("ETag" => $etag, "Last-Modified" => $last_modified)) ) 
		{
			$this->dispatchEvent( new HTTPEvent("collection_get", $request, $response) );
			return $response;
		}

		// Not cached
		$data = $this->get_collection_page($this->pagenr);
		
		$response->http_status = "200 Ok";
		$response->headers["Content-Type"] = "application/atom+xml;type=feed";
		$response->headers["ETag"] = $etag;
		$response->headers["Last-Modified"] = $last_modified;
		$response->response_body = $data;
		
		$this->dispatchEvent( new HTTPEvent("collection_get", $request, $response) );
		
		$this->try_gzip($request, $response);
		
		return $response;
	}
	
	protected function set_name_and_pagenr() {
		$page_template = new App_URITemplate($this->base_uri."{name}/pages/{pagenr}");
		$name_template = new App_URITemplate($this->base_uri."{name}/");

		$matches = $name_template->matches($this->uri);
		if ( $matches !== FALSE ) {
			$this->pagenr = 1;
		} else {
			$matches = $page_template->matches($this->uri);
			if ( $matches !== FALSE ) {
				$this->pagenr = (int)$matches["pagenr"];
				if (  $this->pagenr === 0 && $matches["pagenr"] !== "0" ) {
					throw new HTTPException("File not Found.", 404);
				}
			} else {
				throw new HTTPException("Wrong routing.", 404);
			}
		}
		
		$this->name = $matches["name"];
	}
	
	/*
	 * Feed Generation
	 */
	 public function get_collection_page($pagenr) {
		// Check if the collection exists
		if ( !$this->service->collection_exists($this->feed_uri) ) {
			throw new HTTPException("Collection does not exist.",404);
		}
		
		$key = $this->get_page_key($pagenr);
		if ( !$this->store->exists($key) ) {
			$doc = $this->create_page($pagenr);
			
			$fs = new FeedSerializer();
			$data = $fs->writeToString($doc);
			
			$this->store->store($key, $data);
			
			$pages_list = $this->get_pages_list();
			$pages_list[] = $key;
			$this->save_pages_list($pages_list);
			
			return $data;
		}
		
		return $this->store->get($key);
	}
	
	public function last_modified() {
		return $this->list_last_modified();
	}
	
	protected function create_page($pagenr) {
		$feed = $this->create_feed();
		
		$list = array_reverse($this->get_collection_list());
		
		$total_entries = count($list);
		$start = ($pagenr-1)*$this->page_length;
		$end = $start + $this->page_length;
		if ($end > $total_entries) {
			$end = $total_entries;
		}
		if ($start >= $total_entries && ($total_entries !== 0 || $pagenr > 1)) {
			throw new HTTPException("Page does not exist.",404);
		}
		if ( $pagenr == 0 ) {
			$start = 0;
			$end = $total_entries;
		}
		
		$this->add_links_to_feed($feed, $pagenr, $total_entries);
		
		for ($i=$start; $i<$end; $i++) {
			$uri = new URI($list[$i]["URI"]);

			$entry = new App_Entry($uri, $this);

			$this->add_entry_to_feed($entry->get_document(), $feed);
		}
		
		return $feed;
	}
	protected function create_feed() {

		$feed = $this->get_feed_template();
		
		$domain = $this->uri->components["authority"];

		$id = "tag:".$domain.",".date("Y").":".$this->name;
		$title = $this->service->get_collection_title($this->uri);
		if ($title == "" || $title == FALSE) {
			$title = "$domain $this->name";
		}
		
		$titles = $feed->getElementsByTagName("title");
		$ids = $feed->getElementsByTagName("id");
		$updates = $feed->getElementsByTagName("updated");
		
		// required elements
		if ( $titles->length == 0) {
			$title_el = $feed->createElementNS("http://www.w3.org/2005/Atom","title");
			$title_el->appendChild( $feed->createTextNode( htmlspecialchars($title) ) );
			$feed->documentElement->appendChild($title_el);
		}
		if ( $ids->length == 0) {
			$id_el = $feed->createElementNS("http://www.w3.org/2005/Atom","id");
			$id_el->appendChild( $feed->createTextNode( $id ) );
			$feed->documentElement->appendChild($id_el);
		}
		if ( $updates->length == 0 ) {
			$update_el = $feed->createElementNS("http://www.w3.org/2005/Atom","updated");
			$update_el->appendChild( $feed->createTextNode( date(DATE_ATOM) ) );
			$feed->documentElement->appendChild($update_el);
		}
		
		return $feed;
	}
	private function add_entry_to_feed($entry, $feed) {
		$entry_el = $feed->importNode($entry->documentElement, true);
		
		$feed->documentElement->appendChild($entry_el);
	}
	private function add_links_to_feed($feed, $pagenr, $total) {
		$nr_pages = (int)($total/$this->page_length);
		if ( $total % $this->page_length != 0 ) {
			$nr_pages = $nr_pages + 1;
		}
		
		if ( $pagenr == 0 ) {
			$complete = $feed->createElementNS("http://purl.org/syndication/history/1.0",
													"fh:complete");
			$feed->documentElement->appendChild($complete);
			$this->add_feed_link($feed, "self", $this->get_page_uri(0));
			return;
		}
		
		$this->add_feed_link($feed, "self", $this->get_page_uri($pagenr));
		$this->add_feed_link($feed, "first", $this->get_page_uri(1));
		if ( $nr_pages > 1 ) {
			$this->add_feed_link($feed, "last", $this->get_page_uri($nr_pages));
		}
		if ($pagenr > 1) {
			$this->add_feed_link($feed, "previous", $this->get_page_uri($pagenr-1));
		}
		if ($pagenr < $nr_pages && $nr_pages > 1) {
			$this->add_feed_link($feed, "next", $this->get_page_uri($pagenr+1));
		}
	}
	private function add_feed_link($feed, $rel, $href) {
		$link = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
		$link->setAttribute("rel",$rel);
		$link->setAttribute("href",$href);
		$feed->documentElement->appendChild($link);
	}
	
	/*
	 * Collections list
	 */
	protected function get_collection_list() {
		$key = $this->get_list_key();
		$js = $this->store->get($key);
		if ($js != "") {
			$entries = json_decode($js, TRUE);
		} else {
			$entries = json_decode("[]", TRUE);
		}
		return $entries;
	}
	protected function save_collection_list($list) {
		$js = json_encode($list);
		$this->store->store($this->get_list_key(), $js);
	}
	protected function list_last_modified() {
		$key = $this->get_list_key();
		if ( $this->store->exists($key) ) {
			return $this->store->modified($key);
		} else {
			return time();
		}
	}
	/*
	 * Pages list
	 */
	protected function get_pages_list() {
		$key = $this->get_pagelist_key();
		$js = $this->store->get($key);
		if ($js != "") {
			$entries = json_decode($js, TRUE);
		} else {
			$entries = json_decode("[]", TRUE);
		}
		return $entries;
	}
	protected function save_pages_list($list) {
		$js = json_encode($list);
		$this->store->store($this->get_pagelist_key(), $js);
	}
	
	protected function update_pages() {
		$list = $this->get_pages_list();
		foreach ( $list as $key ) {
			$this->store->remove($key);
		}
		$list = array();
		$this->save_pages_list($list);
		//$this->store->remove_dir($this->base_name()."/pages/");
	}
	
	protected function get_feed_template() {
		if ( file_exists("templates/feed_".$this->name.".xml") ) {
			$feed = DOMDocument::load("templates/feed_".$this->name.".xml");
		} else {
			$feed = DOMDocument::load("templates/feed.xml");
		}
		
		return $feed;
	}
	
	protected function base_name() {
		return $this->base_uri.$this->name."/";
	}
	protected function get_page_key($nr) {
		$key = $this->base_name()."pages/".$nr.".atom";
		
		return $key;
	}
	protected function get_list_key() {
		return $this->base_name()."list/list.json";
	}
	protected function get_pagelist_key() {
		return $this->base_name()."pages/list.json";
	}
	protected function get_page_uri($nr) {
		if ( $nr == 1 ) {
			return $this->feed_uri;
		} else {
			return $this->feed_uri."pages/".$nr;
		}
	}
	
}
