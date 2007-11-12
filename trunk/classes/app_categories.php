<?php
/**
 * Class definition of the App_Categories class
 * @package PAPPI
 */

/**
 * The App_Categories class
 *
 * Category handling
 * @package PAPPI
 */
class App_Categories {
	
	private $service;
	private $store;
	
	/**
	 * Create a new App_Categoriesobject.
	 *
	 * @param App_Servicedoc $service The service document object.
	 * @param App_Categories_Store $store The storage area.
	 */
	public function __construct($service, $store) {
		$this->service = $service;
		$this->store = $store;
	}
	
	/**
	 * Add an entry to the categories listing.
	 *
	 * @param DOMNode $entry The entry.
	 * @param string $uri The URI of the entry.
	 *
	 * @return boolean Returns false if the entry is not allowed in a collection.
	 */
	public function add_entry($entry, $uri) {
		if ( $entry->nodeType == XML_DOCUMENT_NODE ) {
			$entry = $entry->documentElement;
		}
		
		$uri = str_replace($this->service->base_uri,"",$uri);
		
		$ex_uri = explode("/",$uri);
		$name = $this->service->base_uri.$ex_uri[0]."/";
		
		$cats = $this->find_categories($entry);
		
		if ( count($cats) == 0 ) {
			return TRUE;
		}
		
		$time = time(); // should maybe be published date
		
		if ( $this->entry_allowed($cats, $name) ) {
			foreach ( $cats as $cat ) {
				$this->store->add_to_category($cat, $this->store->store_dir."/".$uri, $time);
			}
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function remove_entry($entry, $uri) {
		if ( $entry->nodeType == XML_DOCUMENT_NODE ) {
			$entry = $entry->documentElement;
		}
		
		$uri = str_replace($this->service->base_uri,"",$uri);
		
		$cats = $this->find_categories($entry);
		
		foreach ( $cats as $cat ) {
			$this->store->remove_from_category($cat, $this->store->store_dir."/".$uri);
		}
	}
	
	
	private function find_categories($entry) {
		// find categories in an entry
		$cats = array();
		foreach ( $entry->childNodes as $child ) {
			if ( $child->namespaceURI == "http://www.w3.org/2005/Atom" &&
					$child->localName == "category") {
				
				if ( $child->hasAttribute("term") ) {
					$cats[] = $child->getAttribute("term");
				}
				
			}
		}
		
		return $cats;
	}
	
	private function entry_allowed($cats, $collection_uri) {
		foreach( $cats as $cat ) {
			if ( $this->service->category_allowed($cat, $collection_uri) ) {
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
}

?>