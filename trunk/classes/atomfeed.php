<?php

require_once("appfilestore.php");
require_once("appentry.php");

require_once("appevents.php");
require_once("appuritemplate.php");

require_once("httpresponse.php");
require_once("httpexception.php");

require_once("feedserializer.php");

class Atom_Feed extends EventHTTPResource {
	
	public $name;
	public $base_uri;
	public $store;
	
	protected $service;
	
	public $page_length = 10;
	
	public function __construct($name, $store, $service) {
		$nuri = new URI($name."/");
		parent::__construct($nuri->resolve($service->base_uri));
	
		$this->name = $name;
		
		$this->store = $store;
		$this->service = $service;
		$this->base_uri = $service->base_uri;
	}
	
	public function http_GET($request) {
		$response = new HTTPResponse();
		
		$page_template = new App_URITemplate($this->base_uri.$this->name."/pages/{pagenr}");
		$name_template = new App_URITemplate($this->base_uri.$this->name."/");

		$name_match = $name_template->matches($this->uri);
		if ( $name_match !== FALSE ) {
			$page = 1;
			
		} else {
			$page_match = $page_template->matches($this->uri);
			if ( $page_match !== FALSE ) {
				$page = (int)$page_match["pagenr"];
			} else {
				throw new HTTPException("Wrong routing.", 404);
			}
		}
		
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
		$data = $this->get_collection_page($page);
		
		$response->http_status = "200 Ok";
		$response->headers["Content-Type"] = "application/atom+xml;type=feed";
		$response->headers["ETag"] = $etag;
		$response->headers["Last-Modified"] = $last_modified;
		$response->response_body = $data;
		
		$this->dispatchEvent( new HTTPEvent("collection_get", $request, $response) );
		
		$this->try_gzip($request, $response);
		
		return $response;
	}
	
	/*
	 * Feed Generation
	 */
	 public function get_collection_page($pagenr) {
		// Check if the collection exists
		if ( !$this->service->collection_exists($this->base_uri.$this->name."/") ) {
			throw new HTTPException("Collection does not exist.",404);
		}
		
		$key = $this->base_uri.$this->name."/pages/".$pagenr.".atom";
		if ( !$this->store->exists($key) ) {
			$doc = $this->create_page($pagenr);
			
			$fs = new FeedSerializer();
			$data = $fs->writeToString($doc);
			
			$this->store->store($key, $data);
			
			return $data;
		}
		
		return $this->store->get($key);
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
			$uri = new URI($list[$i]["URI"].".atomentry");
			$uri->absolutize($this->base_uri.$this->name."/");

			$entry = new App_Entry($uri, $this);

			$this->add_entry_to_feed($entry->get_document(), $feed);
		}
		
		return $feed;
	}
	protected function create_feed() {
		if ( file_exists("templates/feed_".$this->name.".xml") ) {
			$feed = DOMDocument::load("templates/feed_".$this->name.".xml");
		} else {
			$feed = DOMDocument::load("templates/feed.xml");
		}
		
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
			$this->add_feed_link($feed, "self", $this->base_uri.$this->name."/pages/0");
			return;
		}
		
		$this->add_feed_link($feed, "first", $this->base_uri.$this->name."/");
		if ( $nr_pages > 1 ) {
			$this->add_feed_link($feed, "last", $this->base_uri.$this->name."/pages/".$nr_pages);
		}
		if ($pagenr > 1) {
			$this->add_feed_link($feed, "self", $this->base_uri.$this->name."/pages/".$pagenr);
			if ( $pagenr-1 === 1 ) {
				$this->add_feed_link($feed, "previous", $this->base_uri.$this->name."/pages/");
			} else {
			  $this->add_feed_link($feed, "previous", $this->base_uri.$this->name."/pages/".($pagenr-1));
			}
		} else {
			$this->add_feed_link($feed, "self", $this->base_uri.$this->name."/");
		}
		if ($pagenr < $nr_pages && $nr_pages > 1) {
			$this->add_feed_link($feed, "next", $this->base_uri.$this->name."/pages/".($pagenr+1));
		}
	}
	private function add_feed_link($feed, $rel, $href) {
		$link = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
		$link->setAttribute("rel",$rel);
		$link->setAttribute("href",$href);
		$feed->documentElement->appendChild($link);
	}
	
	protected function get_collection_list() {
		$key = $this->base_uri.$this->name."/list/list.json";
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
		$this->store->store($this->base_uri.$this->name."/list/list.json",$js);
	}
	protected function list_last_modified() {
		$key = $this->base_uri.$this->name."/list/list.json";
		if ( $this->store->exists($key) ) {
			return $this->store->modified($key);
		} else {
			return time();
		}
	}
	protected function update_pages() {
		$this->store->remove_dir($this->base_uri.$this->name."/pages/");
	}
	
	/*
	 * Extension methods
	 */
	protected function on_collection_get($request, $response) {
	
	}
}
