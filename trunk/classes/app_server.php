<?php
/**
 * The atompub Server file.
 * @package PAPS
 */
 
/**
 * Required: App_Mime and App_Cleanup
 */
require_once("app_mime.php");
require_once("app_cleanup.php");
require_once("app_mimetype.php");
require_once("app_accept_encoding.php");
require_once("app_uri_handler.php");
require_once("app_categories.php");
require_once("app_response.php");

/**
 * The AtomPub server implementation.
 *
 * App_Collection implements the CRUD actions defined in the 
 * {@link http://tools.ietf.org/html/rfc5023 atom publishing protocol}
 * and is the starting point of all actions.
 * @package PAPS
 */
class App_Server {
	/* NS: http://www.w3.org/2007/app , http://www.w3.org/2005/Atom */
	
	private $collection;
	private $response;
	private $request;
	private $store;
	
	public $service;
	public $base_uri;
	
	// Constructor
	/**
	 * Create a new atompub server.
	 *
	 * @param App_Store $store An object that implements the App_Store API.
	 * @param App_Service $service The service description of the server.
	 * @param string $base_uri The location of this server.
	 * @param App_Request $request The App_Request object that has all request information.
	 */
	public function __construct($store, $service, $base_uri, $request) {
		$this->store = $store;
		$this->base_uri = $base_uri;
		$this->request = $request;
		$this->service = $service;
	}
	
	/** 
	 * Write the full collection to the response.
	 * @param string $name The name of the collection.
	 * @return App_Response A response object.
	 */
	public function show_collection($name) {
		return $this->show_collection_page($name, 0);
	}
	
	/**
	 * Write one page of a paged collection to the response.
	 * @param string $name The name of the collection.
	 * @param integer $page The page in the collection.
	 * 
	 * @return App_Reponse The response.
	 */
	public function show_collection_page($name, $page) {
		$this->response = new App_Response();
		
		if ( $this->service->collection_exists($this->base_uri.$name."/") ) {
			
			$lastEdited = gmdate("D, d M Y H:i:s",
					(int)$this->store->collection_last_edited($name))." GMT";
			$etag = '"'.md5($lastEdited).'"';
			
			// Cache
			if ( $this->try_cache($etag, $lastEdited) ) {
				$this->response->headers["Content-Type"] = "application/atom+xml;type=feed";
				
				return $this->response;
			}
			
			// $collection == String 
			$collection = $this->store->get_collection_page($name, $page);
			if ( $collection != "" ) {
				$this->response->http_status = "200 Ok";
				$this->response->headers["Content-Type"] = "application/atom+xml;type=feed";
				$this->response->headers['ETag'] = $etag;
				$this->response->headers['Last-Modified'] = $lastEdited;
				$this->response->response_body = $collection;
				
				$this->try_gzip();
			} else {
				$this->response->http_status = "404 File Not Found";
				$this->response->headers["Content-Type"] = "text/plain";
				$this->response->response_body = "Collection page does not exist";
			}
			
		} else {
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Collection does not exist";		
		}
		
		return $this->response;
	}
	
	/* AtomPub actions on Resources */
	
	/* GET Resource
	 * -> 200 Ok
	 * -> Member representation 
	 */
	/**
	 * Retrieve a resource
	 *
	 * Write a resource (or error code) to the response.
	 * @param string $collection The name of the collection the resource is in.
	 * @param integer $year The year part of the URI of the resource.
	 * @param integer $month The month part of the URI of the resource.
	 * @param integer $day The day part of the URI of the resource.
	 * @param string $name The name part of the URI of the resource.
	 * 
	 * @return App_Reponse The response.
	 */
	public function get_resource($collection, $year, $month, $day, $name) {
		$this->response = new App_Response();
		
		if ( $this->store->resource_exists($collection, $year, $month, $day, $name) ) {
			$amime = new App_Mime();
			$mime = $amime->get_mimetype($name);
			
			$mime_obj = new App_Mimetype($mime);
			if ( $mime_obj->type == "text" ) {
				$mime = $mime."; charset=utf-8";
			}
			
			$lastEdited = gmdate("D, d M Y H:i:s",(int)$this->store->lastEdited)." GMT";
			$etag = '"'.md5($lastEdited).'"';
			
			// Cache
			if ( $this->try_cache($etag, $lastEdited) ) {
				$this->response->headers["Content-Type"] = $mime;
				
				// Collection specific
				$this->collection_specific_read($collection, $this->response);
				
				return $this->response;
			}
			
			$res = $this->store->get_resource($collection, $year, $month, $day, $name);
			
			$this->response->http_status = "200 Ok";
			$this->response->headers["Content-Type"] = $mime;
			$this->response->headers['ETag'] = $etag;
			$this->response->headers['Last-Modified'] = $lastEdited;
			$this->response->response_body = $res;
			
			if ( $mime_obj->type == "text" || 
					($mime_obj->type == "application"  && strstr($mime_obj->subtype,"xml") != FALSE ) ) {
				$this->try_gzip();
			}
			
			// Collection specific
			$this->collection_specific_read($collection, $this->response);
			
		} else {
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Resource does not exist.";
		}
		
		return $this->response;
	}
	
	/* POST to collection
	 * -> 201 Created
	 * -> Location: Member entry URI 
	 * -> Member representation
	 */
	/**
	 * Create a new resource in a collection.
	 *
	 * Create a new resource in a collection. Checks if the added resource is allowed in the
	 * collection.
	 * @param string $collection The name of the collection in which the resource has to be
	 * created.
	 * 
	 * @return App_Reponse The response.
	 */
	public function create_resource($collection) {
		$year = date("Y");
		$month = date("m");
		$day = date("d");
		
		return $this->create_resource_by_date($collection,$year,$month,$day);
	}
	
	// create a resource with a specific date
	/**
	 * Create a new resource in a collection with a specific date.
	 *
	 * Create a new resource in a collection with a particular date in the URI. Checks if the 
	 * added resource is allowed in the collection. Might apply collection specific processing to
	 * an entry if the collection_specific_dir property of App_Service is set.
	 * @param string $collection The name of the collection in which the resource has to be
	 * created.
	 * 
	 * @return App_Reponse The response.
	 */
	public function create_resource_by_date($collection, $year, $month, $day) {
		$this->response = new App_Response();
		
		// Check if the collection exists
		if ( !$this->service->collection_exists($this->base_uri.$collection."/") ) {
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Collection does not exist.";
			
			return $this->response;
		}
	
		// Get a name from the Slug header
		if ( array_key_exists("Slug", $this->request->headers) ) {
			$name = rawurlencode(
						preg_replace(
							"/([\;\/\?\:\@\&\=\+\$\, ])/",
							"-",
							rawurldecode($this->request->headers["Slug"])
						)
			);
		} else {
			$name = rand();
		}
		if ($name == "") {
			$name = rand();
		}
		
		// Can't create a resource that already exists
		// A .atomentry always exists if the resource exists
		if ( $this->store->resource_exists($collection,$year,$month,$day,$name.".atomentry") ) {
			$this->response->http_status = "409 Conflict";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Resource already exists. Use PUT to update.";
		
			return $this->response;
		}
		
		// Check if the collection accepts a given media type
		if ( $this->is_supported_media_type($collection) ) {
			if ( $this->mimetype_is_atom() ) {
				// Add an atom entry
				// feeds also go down this path, but they will be filtered out later on
				$this->create_entry_resource($collection, $year, $month, $day, $name);
			} else {
				// Media entry
				$this->create_media_resource($collection, $year, $month, $day, $name);
			}
		} else {
			$this->response->http_status = "415 Unsupported Media Type";
			$this->response->headers["Content-Type"]="text/plain";
			$this->response->response_body = "Unsupported Media Type.";
		}
		
		return $this->response;
	}
	
	/*
	 * Add an atom entry to a collection
	 */
	private function create_entry_resource($collection, $year, $month, $day, $name) {
		try {
			// test for well-formed XML
			$entry_doc = DOMDocument::loadXML($this->request->request_body,
					LIBXML_NOWARNING+LIBXML_NOERROR);
			if( !isset($entry_doc) || $entry_doc == FALSE ) {
				throw new Exception("XML Parsing failed");
			}
			
			$amime = new App_Mime();
			$extension = $amime->get_extension($this->request->headers["Content-Type"]);
			if ( !$amime->is_atom_entry($entry_doc) ) {
				// atom file, but no entry -> create media entry
				return $this->create_media_resource($collection, $year, $month, $day, $name);
			}
			
			// edit link
			$link = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","link");
			$link->setAttribute("rel","edit");
			$link->setAttribute("href",$this->base_uri."$collection/$year/$month/$day/$name.atomentry");
			$entry_doc->documentElement->appendChild($link);
			
			// clean up
			$cleaner = new App_Cleanup($this->base_uri,
				$this->base_uri."$collection/$year/$month/$day/$name");
			$cleaner->clean_entry($entry_doc);
			
			// Collection specific
			$this->collection_specific_create($collection, $entry_doc);
			
			// categories
			if ( $this->store instanceof App_Categories_Store) {
				$cats = new App_Categories($this->service, $this->store);
				
				$allow = $cats->add_entry($entry_doc,
					$this->base_uri."$collection/$year/$month/$day/$name.atomentry");
				
				if ( $allow !== TRUE ) {
					$this->response->http_status = "412 Precondition Failed";
					$this->response->headers["Content-Type"] = "text/plain";
					$this->response->response_body = "Category not allowed in collection.";
					return;
				}
			}
			
			// Save to store
			$this->store->store_resource($collection,$year,$month,$day,
					$name.".atomentry", $entry_doc->saveXML());
			
			// Headers
			$this->response->http_status = "201 Created";
			$this->response->headers["Content-Type"] = $this->request->headers["Content-Type"];
			$this->response->headers["Location"] = 
					$this->base_uri."$collection/$year/$month/$day/$name.atomentry";
			$this->response->headers["Content-Location"] = 
					$this->base_uri."$collection/$year/$month/$day/$name.atomentry";
			$this->response->response_body = $entry_doc->saveXML();
			
		} catch (Exception $e) {
			$this->response->http_status = "400 Bad Request";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Error creating resource, possibly malformed XML.";
		}
	}
	
	/*
	 * Add a media entry to a collection
	 */
	private function create_media_resource($collection, $year, $month, $day, $name) {
		
		$amime = new App_Mime();
		$content_type = $this->request->headers["Content-Type"];
		$extension = $amime->get_extension($content_type);
		
		$mime_obj = new App_Mimetype($content_type);
		if ( $mime_obj->type == "text" ) {
			if ( array_key_exists("charset",$mime_obj->parameters) ) {
				$charset = $mime_obj->parameters["charset"];
				
				$this->request->request_body = iconv($charset, "utf-8", $this->request->request_body);
			}
		}
		
		$media_link_uri = $this->base_uri."$collection/$year/$month/$day/$name.atomentry";
		$media_entry_uri = $this->base_uri."$collection/$year/$month/$day/$name.$extension";
		
		// id
		$domain = explode("/", str_replace("http://","",$this->base_uri));
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		$id = "tag:".$domain.",".$year."-".$month."-".$day.":".$collection."/".$name;
		
		// create media link entry
		$template = DOMDocument::loadXML("<?xml version=\"1.0\" encoding=\"utf-8\"?>
	<entry xmlns=\"http://www.w3.org/2005/Atom\">
	<title/>
	<id/>
	<updated/>
	<published/>
	<author><name></name></author>
	<summary type=\"text\"/>
	<content type=\"\" src=\"\"/>
	<link rel=\"edit-media\" href=\"\"/>
	<link rel=\"edit\" href=\"\"/>
</entry>");
		
		// required fields
		$template->getElementsByTagName("title")->item(0)->appendChild( 
				$template->createTextNode( rawurldecode($name) ) );
		$template->getElementsByTagName("id")->item(0)->appendChild( 
				$template->createTextNode($id) );
		$template->getElementsByTagName("updated")->item(0)->appendChild( 
				$template->createTextNode( date(DATE_ATOM) ) );
		$template->getElementsByTagName("published")->item(0)->appendChild( 
				$template->createTextNode( date(DATE_ATOM) ) );
		$template->getElementsByTagName("content")->item(0)->setAttribute("type",$content_type);
		$template->getElementsByTagName("content")->item(0)->setAttribute("src",$media_entry_uri);
		$template->getElementsByTagName("link")->item(0)->setAttribute("href",$media_entry_uri);
		$template->getElementsByTagName("link")->item(1)->setAttribute("href",$media_link_uri);
		
		// Collection specific
		$this->collection_specific_create($collection, $template);
		
		// add to store
		$this->store->store_resource($collection,$year,$month,$day,
				$name.".atomentry", $template->saveXML());
		$this->store->store_resource($collection,$year,$month,$day,
				$name.".".$extension,$this->request->request_body);
				
		// Headers
		$this->response->http_status = "201 Created";
		$this->response->headers["Content-Type"] = $this->request->headers["Content-Type"];
		$this->response->headers["Location"] = 
				$this->base_uri."$collection/$year/$month/$day/$name.atomentry";
		$this->response->headers["Content-Location"] = 
					$this->base_uri."$collection/$year/$month/$day/$name.atomentry";
		$this->response->response_body = $template->saveXML();

	}
	
	/* PUT a resource
	 * -> 200 Ok 
	 */
	/**
	 * Update a resource
	 *
	 * Update a resource in a collection. Might apply collection specific processing to
	 * an entry if the collection_specific_dir property of App_Service is set.
	 * @param string $collection The name of the collection the resource is in.
	 * @param integer $year The year part of the URI of the resource.
	 * @param integer $month The month part of the URI of the resource.
	 * @param integer $day The day part of the URI of the resource.
	 * @param string $name The name part of the URI of the resource.
	 * 
	 * @return App_Reponse The response.
	 */
	public function update_resource($collection, $year, $month, $day, $name) {
		$this->response = new App_Response();
		
		if ($this->store->resource_exists($collection,$year,$month,$day,$name)) {
			$lastEdited = gmdate("D, d M Y H:i:s",(int)$this->store->lastEdited)." GMT";
			$etag = '"'.md5($lastEdited).'"';
			
			/* Requires If-Match to match!!!*/
			if ( (!array_key_exists('If-Match',$this->request->headers)) || 
					( array_key_exists('If-Match',$this->request->headers) && 
						$etag != str_replace(";gzip","",$this->request->headers['If-Match']) ) ) {
				$this->response->http_status = "412 Precondition Failed";
				$this->response->headers["Content-Type"] = "text/plain";
				$this->response->response_body = "Not the most recent version in cache.";
				
				return $this->response;
			}
			
			
			$amime = new App_Mime();
			$mime = $amime->get_mimetype($name);
			$extension = $amime->get_extension($mime);
			
			$data = $this->request->request_body;
			try {
				if ( $mime == "application/atom+xml;type=entry" ) {
					$entry_doc = DOMDocument::loadXML($data, LIBXML_NOWARNING+LIBXML_NOERROR);
					if ($entry_doc == FALSE) {
						throw new Exception("XML Parsing failed!");
					}
					if ($entry_doc->documentElement->namespaceURI != "http://www.w3.org/2005/Atom" ||
							$entry_doc->documentElement->localName != "entry") {
						throw new Exception("Root must be atom:entry.");
					}
					
					// clean up
					$cleaner = new App_Cleanup($this->base_uri, 
							$this->base_uri."$collection/$year/$month/$day/$name");
					$cleaner->clean_entry($entry_doc);
					
					// find original
					$original_data = $this->store->get_resource($collection,
							$year, $month, $day, $name);
					$original_doc = DOMDocument::loadXML($original_data);
					
					//check if media entry
					$is_media_link = $amime->is_media_link($original_doc);
					
					$urih = new App_Uri_Handler();
					$entry_uri = $this->base_uri."$collection/$year/$month/$day/$name";
					
					// URI's
					if ($is_media_link == TRUE) {
						
						$children = $original_doc->documentElement->childNodes;
						foreach ( $children as $child ) {
							// link[@rel='edit-media']
							if( $child->nodeType == XML_ELEMENT_NODE 
									&& $child->localName == "link"
									&& $child->namespaceURI == "http://www.w3.org/2005/Atom") {
								if (strcasecmp(trim($child->getAttribute("rel")),"edit-media") == 0 ) {
									// is always set
									$media_entry_uri = $urih->resolve_uri(
										$child->getAttributeNode("href"), $entry_uri);
								}
							// content[@src | @type]
							} else if ( $child->nodeType == XML_ELEMENT_NODE 
									&& $child->localName == "content"
									&& $child->namespaceURI == "http://www.w3.org/2005/Atom") {
								$orig_content_type = $urih->resolve_uri(
									$child->getAttributeNode("type"), $entry_uri);
								$orig_content_src = $urih->resolve_uri(
									$child->getAttributeNode("src"), $entry_uri);
							}
						}
					} // -> original uri's are now always known
					
					
					// make sure link-edit(-media), content src/type still match
					$edit_found = FALSE;
					$edit_media_found = FALSE;
					$content_found = FALSE;
					
					$children = $entry_doc->documentElement->childNodes;
					foreach ($children as $child) {
						if( $child->nodeType == XML_ELEMENT_NODE && $child->localName == "link"
							&& $child->namespaceURI == "http://www.w3.org/2005/Atom") {
							// edit
							if ( strcasecmp(trim($child->getAttribute("rel")), "edit") == 0) {
								$child->setAttribute("href",$entry_uri);
								$edit_found = TRUE;
							}
							// edit-media
							if ( $is_media_link == TRUE) {
								if ( strcasecmp(trim($child->getAttribute("rel")),"edit-media") == 0 ) {
									$child->setAttribute("href",$media_entry_uri);
									$edit_media_found = TRUE;
								}
							}
						} else if( $child->nodeType == XML_ELEMENT_NODE 
								&& $child->localName == "content"
								&& $child->namespaceURI == "http://www.w3.org/2005/Atom"
								&& $is_media_link) {
							$child->setAttribute("src",$orig_content_src);
							$child->setAttribute("type",$orig_content_type);
							$content_found = TRUE;
						}
					}
					
					// make sure to add link rels if they don't exist
					if ( $edit_found == FALSE ) {
						// edit link
						$link_e = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","link");
						$link_e->setAttribute("rel","edit");
						$link_e->setAttribute("href",$entry_uri);
						$entry_doc->documentElement->appendChild($link_e);
						
					}
					if ( $edit_media_found == FALSE && $is_media_link == TRUE ) {
						$link_m = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","link");
						$link_m->setAttribute("rel","edit-media");
						$link_m->setAttribute("href",$media_entry_uri);
						$entry_doc->documentElement->appendChild($link_m);				
					}
					if ( $content_found == FALSE && $is_media_link == TRUE ) {
						$ctt_m = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","content");
						$ctt_m->setAttribute("type",$orig_content_type);
						$ctt_m->setAttribute("src",$orig_content_src);
						$entry_doc->documentElement->appendChild($ctt_m);
					}
					
					// categories
					if ( $this->store instanceof App_Categories_Store) {
						$cats = new App_Categories($this->service, $this->store);
						
						$cats->remove_entry($original_doc,
							$this->base_uri."$collection/$year/$month/$day/$name");
							
						$cats->add_entry($entry_doc,
							$this->base_uri."$collection/$year/$month/$day/$name");
					}
					
					// Collection specific
					$this->collection_specific_update($collection, $entry_doc);
					
					$this->store->store_resource($collection,
							$year,$month,$day,$name,$entry_doc->saveXML());
				} else {
					// also update corresponding media link entry
					$exploded_name = explode(".",$name);
					$entry_name = $exploded_name[0].".atomentry";
					
					// update last modified date of media link entry
					$entry_data = $this->store->get_resource($collection,
							$year, $month, $day, $entry_name);
					$this->store->store_resource($collection,$year,$month,$day,$entry_name,$entry_data);
					
					// update actual resource
					$this->store->store_resource($collection,$year,$month,$day,$name,$data);
				}
				
				$this->response->http_status = "200 Ok";
				$this->response->response_body = "";
				
			} catch (Exception $e) {
				$this->response->http_status = "400 Bad Request";
				$this->response->headers["Content-Type"] = "text/plain";
				$this->response->response_body = $e->getMessage();
				return $this->response;
			}
			
		} else {
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "File Not Found";
		}
		
		return $this->response;
	}
	
	/* DELETE resource
	 * -> 200 Ok 
	 */
	/**
	 * Remove a resource
	 *
	 * Remove a resource from a collection. To delete a media entry, remove the corresponding
	 * media link entry.
	 * @param string $collection The name of the collection the resource is in.
	 * @param integer $year The year part of the URI of the resource.
	 * @param integer $month The month part of the URI of the resource.
	 * @param integer $day The day part of the URI of the resource.
	 * @param string $name The name part of the URI of the resource.
	 * 
	 * @return App_Reponse The response.
	 */
	public function remove_resource($collection, $year, $month, $day, $name) {
		$this->response = new App_Response();
		
		if ( $this->store->resource_exists($collection, $year, $month, $day, $name) ) {
			$res_text = $this->store->get_resource($collection, $year, $month, $day, $name);
			$res = DOMDocument::loadXML($res_text, LIBXML_NOWARNING+LIBXML_NOERROR);
			
			// should be an atom entry
			$amime = new App_Mime();
			if ( ($res != FALSE) && $amime->is_atom_entry($res) ) {
				// Collection specific
				$this->collection_specific_delete($collection, $res);
				
				// check if media entry
				$links = $res->getElementsByTagNameNS("http://www.w3.org/2005/Atom","link");
		
				foreach ($links as $link) {
					if ( strcasecmp(trim($link->getAttribute("rel")), "edit-media") == 0) {
						$media_uri = $link->getAttribute("href");
						
						// explode uri
						$exploded_name = explode(".",$media_uri);
						$ext = $exploded_name[count($exploded_name)-1];
						
						// remove media entry
						$this->store->remove_resource(
							$collection,
							$year,
							$month,
							$day,
							str_replace("atomentry",$ext,$name)
						);
					}
				}
				
				// categories
				if ( $this->store instanceof App_Categories_Store) {
					$cats = new App_Categories($this->service, $this->store);
					
					$cats->remove_entry($res,
						$this->base_uri."$collection/$year/$month/$day/$name");
				}
				
				// remove actual atom entry
				$this->store->remove_resource($collection, $year, $month, $day, $name);
			
				$this->response->http_status = "200 Ok";
				$this->response->headers["Content-Type"] = "text/plain";
				$this->response->headers["Cache-Control"]="no-cache";
				$this->response->response_body = "Resource Removed";
			} else {
				// no atom entry
				$this->response->http_status = "400 Bad Request";
				$this->response->headers["Content-Type"] = "text/plain";
				$this->response->response_body = "Can only delete atom entries.";
			}

		} else {
			// Resource does not exist
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Resource does not exist.";
		}
		
		return $this->response;
	}
	
	/*
	 * 
	 * @return App_Reponse The response.
	 */
	public function show_category_page($name, $page) {
		$this->response = new App_Response();
		
		$lastEdited = gmdate("D, d M Y H:i:s",
				(int)$this->store->category_last_edited($name))." GMT";
		$etag = '"'.md5($lastEdited).'"';
		
		// Cache
		if ( $this->try_cache($etag, $lastEdited) ) {
			$this->response->headers["Content-Type"] = "application/atom+xml;type=feed";
			
			return $this->response;
		}
		
		// $collection == String 
		$category = $this->store->get_category_page($name, $page);
		if ( $category != "" ) {
			$this->response->http_status = "200 Ok";
			$this->response->headers["Content-Type"] = "application/atom+xml;type=feed";
			$this->response->headers['ETag'] = $etag;		
			$this->response->response_body = $category;
			
			$this->try_gzip();
		} else {
			$this->response->http_status = "404 File Not Found";
			$this->response->headers["Content-Type"] = "text/plain";
			$this->response->response_body = "Collection page does not exist";
		}
		
		return $this->response;
	}
	
	private function mimetype_is_atom() {
		return !(stristr($this->request->headers["Content-Type"],"application/atom+xml") === FALSE);
	}
	
	private function is_supported_media_type($collection) {
		
		return $this->service->mimetype_accepted($this->request->headers["Content-Type"], 
				$this->base_uri.$collection."/");
	}
	
	private function try_gzip() {
		if ( array_key_exists("Accept-Encoding", $this->request->headers) ){
			$accepted = new App_Accept_Encoding($this->request->headers["Accept-Encoding"]);
			
			$pref = $accepted->preferred( array("gzip"=>1,"identity"=>0.5) );
			if ($pref == "gzip") {
				$this->response->response_body = gzencode($this->response->response_body);
				$this->response->headers["Content-Encoding"] = "gzip";
				$this->response->headers["Vary"] = "Content-Encoding";
				
				if ( array_key_exists("ETag",$this->response->headers) ) {
					$this->response->headers['ETag'] = 
						'"'.str_replace("\"","",$this->response->headers['ETag']).
						";".$this->response->headers["Content-Encoding"].'"';
				}
			}
		}
	}
	
	private function try_cache($etag, $last_modified) {
		if (array_key_exists('If-None-Match',$this->request->headers)) {
			$req_etag = str_replace(";gzip","",$this->request->headers['If-None-Match']);

			if ( $etag == $req_etag ) {
				
				$this->response->http_status = "304 Not Modified";
				
				if ( !array_key_exists("Cache-Control",$this->response->headers) ) {
					$this->response->headers['Cache-Control'] = "must-revalidate";
				}
				
				// ETag for GZipped version should be different
				if ( array_key_exists("Content-Encoding",$this->response->headers) ) {
					$etag = '"'.str_replace("\"","",$etag).";".$this->response->headers["Content-Encoding"].'"';
				}
				
				$this->response->headers['ETag'] = $etag;
				$this->response->headers['Last-Modified'] = $last_modified;
				
				$this->response->response_body = "";
				return TRUE;
			}
		} else if (array_key_exists('If-Modified-Since',$this->request->headers)) {
			$req_mod = strtotime($this->request->headers['If-Modified-Since']);
			
			if ( $req_mod >= strtotime($last_modified) ) {
				$this->response->http_status = "304 Not Modified";
				
				if ( !array_key_exists("Cache-Control",$this->response->headers) ) {
					$this->response->headers['Cache-Control'] = "must-revalidate";
				}
				
				// ETag for GZipped version should be different
				if ( array_key_exists("Content-Encoding",$this->response->headers) ) {
					$etag = '"'.str_replace("\"","",$etag).";".$this->response->headers["Content-Encoding"].'"';
				}
				
				$this->response->headers['ETag'] = $etag;
				$this->response->headers['Last-Modified'] = $last_modified;
				
				$this->response->response_body = "";
				return TRUE;
			}
		}
		return FALSE;
	}
	
	private function collection_specific($collection) {
		if ( isset($this->service->collection_specific_dir) ){
			$dir = $this->service->collection_specific_dir;
			
			if ( file_exists($dir."/".$collection.".php") ) {
				require_once($dir."/".$collection.".php");
				
				$name = $collection."_specific";
				$specific = new $name();
				
				return $specific;
				$specific->on_create($entry);
			}
		}
		return NULL;
	}
	
	private function collection_specific_read($collection, $response) {
		$specific = $this->collection_specific($collection);
		if ($specific != NULL) {
			$specific->on_read($response);
		}
	}
	
	private function collection_specific_create($collection, $entry) {
		$specific = $this->collection_specific($collection);
		if ($specific != NULL) {
			$specific->on_create($entry);
		}

	}
	private function collection_specific_update($collection, $entry) {
		$specific = $this->collection_specific($collection);
		if ($specific != NULL) {
			$specific->on_update($entry);
		}

	}
	private function collection_specific_delete($collection, $entry) {
		$specific = $this->collection_specific($collection);
		if ($specific != NULL) {		
			$specific->on_delete($entry);
		}
	}

}

?>
