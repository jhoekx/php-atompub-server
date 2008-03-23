<?php

require_once("httpresponse.php");
require_once("httpresource.php");
require_once("httpexception.php");

require_once("appentry.php");
require_once("appmediaresource.php");
require_once("appmimetype.php");
require_once("appcleaner.php");

require_once("atomfeed.php");
require_once("feedserializer.php");

class App_Collection extends Atom_Feed {
	
	public function __construct($name, $store, $service) {
		parent::__construct($name, $store, $service);
	}
	
	/*
	 * HTTP Methods
	 */
	public function http_POST($request) {
		$response = new HTTPResponse();
		
		if ( !$request->header_exists("Content-Type") ) {
			throw new HTTPException("No Content-Type header sent.", 400);
		}
		
		$name = $this->give_name($this->parse_slug($request));
		$content_type = new App_Mimetype($request->headers["Content-Type"]);
		
		$entry = $this->create_entry($name, $request->request_body, $content_type);
		
		$time = $entry->last_modified();
		
		$etag = '"'.md5($time).'"';
		$last_modified = $this->time_to_gmt($time);
		
		$response->http_status = "201 Created";
		$response->headers["Content-Type"] = $content_type;
		$response->headers["Location"] = $entry->uri;
		$response->headers["Content-Location"] = $entry->uri;
		$response->headers["ETag"] = $etag;
		$response->headers["Last-Modified"] = $last_modified;
		
		$fs = new FeedSerializer();
		$response->response_body = $fs->writeToString($entry->get_document());
		
		$this->dispatchEvent( new HTTPEvent("collection_post", $request, $response) );
		
		$this->try_gzip($request, $response);
		
		return $response;
	}
	
	/*
	 * Collection methods
	 */
	public function get_entry($uri) {
		$r_uri = $uri->base_on($this->uri);

		if (!$this->store->exists($uri)) {
			throw new HTTPException("Resource does not exist.",404);
		}
		
		if ($r_uri->get_extension() == "atomentry") {
			$entry = new App_Entry($uri, $this);
		} else {
			$entry = new App_MediaResource($uri, $this);
		}
		
		$this->attachEvents($entry);
		
		$this->dispatchEvent( new APPEvent("entry_open", $entry) );
		
		return $entry;
	}
	
	public function create_entry($name, $data, $content_type) {
		// Check if the collection exists
		if ( !$this->service->collection_exists($this->uri) ) {
			throw new HTTPException("Collection does not exist.", 404);
		}
		
		// Check if the collection accepts a given media type
		if ( !$this->is_supported_media_type($content_type) ) {
			throw new HTTPException("Unsupported Media Type",415);
		}
		
		if ( $this->mimetype_is_atom($content_type) ) {
			// Add an atom entry
			// feeds also go down this path, but they will be filtered out later on
			$entry = $this->create_entry_resource($name, $data);
		} else {
			// Media entry
			$entry = $this->create_media_resource($name, $data, $content_type);
		}
		
		$this->attachEvents($entry);
		
		$this->dispatchEvent( new APPEvent("entry_create", $entry) );
		
		return $entry;
	}
	
	public function last_modified() {
		return $this->list_last_modified();
	}
	
	public function is_supported_media_type($content_type) {
		return $this->service->mimetype_accepted($content_type, $this->uri);
	}
	
	/*
	 * To maintain collection state.
	 */
	public function add_entry($event) {
		$list = $this->get_collection_list();
		$entry = $event->entry;
		
		// check if the entry already exists
		foreach( $list as $item ) {
			if ($item["URI"] == $entry->name ) {
				throw new HTTPException("Entry already exists.", 409);
			}
		}
		
		array_push($list, array("URI"=>$entry->name, "Edit"=>time()) );
		
		$this->save_collection_list($list);
		$this->update_pages();
		
		$entry->save(); // save only if everything successful.
	}
	
	public function update_entry($event) {
		$list = $this->get_collection_list();
		$entry = $event->entry;
		
		// find entry
		for ($i=0; $i<count($list); $i++) {
			if ($list[$i]["URI"] == $entry->name ) {
				$index = $i;
			}
		}
		
		// last edited -> first entry in collection
		if (isset($index)) {
			$item = array_splice($list,$index,1);
			$item[0]["Edit"] = time();
			array_push($list, $item[0]);
		}
		
		$this->save_collection_list($list);
		$this->update_pages();
	}
	
	public function remove_entry($event) {
		$list = $this->get_collection_list();
		$entry = $event->entry;
		
		for ($i=0; $i<count($list); $i++) {
			if ($list[$i]["URI"] == $entry->name ) {
				$index = $i;
			}
		}
		
		if (isset($index)) {
			array_splice($list,$index,1);
		}
		
		$this->save_collection_list($list);
		$this->update_pages();
	}
	
	/*
	 * Entry Creation
	 */
	protected function parse_slug($request) {
		// Get a name from the Slug header
		if ( $request->header_exists("Slug") ) {
			$name = rawurlencode(
						preg_replace(
							"/([\;\/\?\:\@\&\=\+\$\, ])/",
							"-",
							rawurldecode($request->headers["Slug"])
						)
			);
		} else {
			$name = rand();
		}
		if ($name == "") {
			$name = rand();
		}
		return utf8_encode($name);
	}
	
	protected function mimetype_is_atom($content_type) {
		return !(stristr($content_type,"application/atom+xml") === FALSE);
	}
	protected function is_atom_entry($doc) {
		$cA = ($doc->documentElement->localName == "entry");
		$cB = ($doc->documentElement->namespaceURI == "http://www.w3.org/2005/Atom");
		return $cA && $cB;
	}
	
	protected function create_entry_resource($name, $data) {
		// test for well-formed XML
		$entry_doc = DOMDocument::loadXML($data, LIBXML_NOWARNING+LIBXML_NOERROR);
		if( !isset($entry_doc) || $entry_doc == FALSE ) {
			throw new HTTPException("XML Parsing failed!",400);
		}
		
		if ( !$this->is_atom_entry($entry_doc) ) {
			// atom file, but no entry -> disallow
			throw new HTTPException("Adding feeds to a collection is undefined",400);
		}
		
		$uri = new URI($this->base_uri.$this->name."/".$name.".atomentry");
		
		// link[@rel='edit']
		$link = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","link");
		$link->setAttribute("rel","edit");
		$link->setAttribute("href", $uri);
		$entry_doc->documentElement->appendChild($link);
		
		// app:edited
		$edit = $entry_doc->createElementNS("http://www.w3.org/2007/app","app:edited");
		$edit->appendChild( $entry_doc->createTextNode(date(DATE_ATOM,time())) );
		$entry_doc->documentElement->appendChild($edit);
		
		// clean up
		$cleaner = new App_Cleaner($uri, $this->base_uri);
		$cleaner->make_conforming($entry_doc);
		
		$entry = new App_Entry($uri, $this);
		$entry->doc = $entry_doc;
		
		return $entry;
	}
	
	protected function create_media_resource($name, $data, $content_type) {
	
		// media type
		$extension = $content_type->get_extension();
		
		// convert to utf-8
		if ( $content_type->type == "text" ) {
			if ( $content_type->parameter_exists("charset") ) {
				$charset = $content_type->parameters["charset"];
				
				$data = iconv($charset, "utf-8", $data);
			}
		}
		
		$doc = DOMDocument::load("templates/medialink.xml");
		$media_link_uri = new URI($this->base_uri.$this->name."/$name.atomentry");
		$media_resource_uri = new URI($this->base_uri.$this->name."/$name.".$extension);
		
		// id
		$domain = explode("/", str_replace("http://","",$this->base_uri));
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		$year = date("Y"); $month = date("m"); $day = date("d");
		$id = "tag:".$domain.",".$year."-".$month."-".$day.":".$this->name."/".$name;
		
		// required fields
		$doc->getElementsByTagName("title")->item(0)->appendChild( 
				$doc->createTextNode( utf8_encode(rawurldecode($name)) ) );
		$doc->getElementsByTagName("id")->item(0)->appendChild( 
				$doc->createTextNode($id) );
		$doc->getElementsByTagName("updated")->item(0)->appendChild( 
				$doc->createTextNode( date(DATE_ATOM) ) );
		$doc->getElementsByTagName("published")->item(0)->appendChild( 
				$doc->createTextNode( date(DATE_ATOM) ) );
		$doc->getElementsByTagName("content")->item(0)->setAttribute("type",$content_type);
		$doc->getElementsByTagName("content")->item(0)->setAttribute("src",$media_resource_uri);
		$doc->getElementsByTagName("link")->item(0)->setAttribute("href",$media_resource_uri);
		$doc->getElementsByTagName("link")->item(1)->setAttribute("href",$media_link_uri);
		
		// app:edited
		$edit = $doc->createElementNS("http://www.w3.org/2007/app","app:edited");
		$edit->appendChild( $doc->createTextNode(date(DATE_ATOM,time())) );
		$doc->documentElement->appendChild($edit);
		
		// media link entry
		$media_link = new App_Entry($media_link_uri, $this);
		$media_link->doc = $doc;
		
		// media resource
		$media_resource = new App_MediaResource($media_resource_uri, $this);
		$media_resource->content = $data;
		
		$media_link->save();
		$media_resource->save();
		
		return $media_link;
	}
	
	/*
	 * Extension methods
	 */
	protected function give_name($slug) {
		return $slug;
	}
	
	public function propagateEvent($event) {
		$this->dispatchEvent($event);
	}
	
	protected function attachEvents($entry) {
		$events = array(
			"entry_update",
			"entry_remove",
			"entry_get",
			"entry_put",
			"entry_delete"
		);
		foreach ($events as $event) {
			$entry->addEventListener($event, $this, "propagateEvent");
		}
		
		$this->addEventListener("entry_create", $this, "add_entry");
		$entry->addEventListener("entry_update", $this, "update_entry");
		$entry->addEventListener("entry_remove", $this, "remove_entry");
	}
}
