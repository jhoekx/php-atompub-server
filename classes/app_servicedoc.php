<?php

require_once("app_mimetype.php");
require_once("app_uri_handler.php");

class App_Servicedoc {
	
	private $filename;
	private $doc;
	public $base_uri;
	public $collection_specific_dir;
	
	public function __construct($filename, $base_uri) {
		$this->filename = $filename;
		$this->base_uri = $base_uri;
	}
	
	// $name is a full URI
	public function collection_exists($name) {
		$col = $this->find_collection($name);
		if ( $col !== FALSE ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function get_servicedoc() {
		if ( !isset($this->doc) ) {
			$this->doc = DOMDocument::load($this->filename);
		}
		return $this->doc;
	}
	public function save_servicedoc() {
		$this->doc->save($this->filename);
	}
	
	public function mimetype_accepted($mimetype, $collection_name) {
		if ( !isset($this->doc) ) {
			$this->doc = DOMDocument::load($this->filename);
		}
		$cols = $this->doc->getElementsByTagNameNS("http://www.w3.org/2007/app","collection");
		
		$mime_test = new App_Mimetype($mimetype);
		$urih = new App_Uri_Handler();
		$service_base = $this->base_uri;
		
		foreach ( $cols as $col ) {
			if ( $urih->resolve_uri($col->getAttributeNode("href"),$service_base) == $collection_name ) {
				
				$accepts = $col->getElementsByTagNameNS("http://www.w3.org/2007/app","accept");
				
				// No accepts -> only application/atom+xml is allowed
				if ($accepts->length == 0) {
					$accepted_type = new App_Mimetype("application/atom+xml");
					if ( $mime_test->is_a($accepted_type) ) {
						return TRUE;
					}
				}
				
				// 1 empty accept -> nothing allowed
				if ($accepts->length == 1) {
					if (trim($accepts->item(0)->textContent) == "") {
						return FALSE;
					}
				}
				
				foreach ( $accepts as $accept ) {
					$accepted_text = $accept->textContent;
					
					$accepted_type = new App_Mimetype($accepted_text);
					
					if ( $mime_test->is_a($accepted_type) ) {
						return TRUE;
					}
				}
				
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Check if a given term is allowed in a collection.
	 * Does not resolve external app:categories
	 *
	 * @param string $term The term.
	 * @param string $collection The URI of the collection.
	 *
	 * @return boolean Indicates whether a category is allowed.
	 */
	public function category_allowed($term, $collection_uri) {
		$col = $this->find_collection($collection_uri);

		if ( $col === FALSE ) {
			return FALSE;
		}

		$catlist = $col->getElementsByTagNameNS("http://www.w3.org/2007/app","categories");

		if ( $catlist->length > 0 ) {
			$list = $catlist->item(0);

			// if fixed==no -> allow
			if ( $list->hasAttribute("fixed") ) {
				if ( trim($list->getAttribute("fixed"))=="no" ) {
					return TRUE;
				} else {
					$cats = $list->getElementsByTagNameNS("http://www.w3.org/2005/Atom","category");

					foreach ( $cats as $cat ) {
						
						if (trim($cat->getAttribute("term"))==$term) {
							return TRUE;
						}
					}
					
					return FALSE;
				}
			} else {
				return TRUE;
			}
			
		} else { // no categories
			return TRUE;
		}

	}
	
	/**
	 * Get the title of a collection.
	 *
	 * @param string $uri The URI of the collection.
	 *
	 * @return string The title.
	 */
	public function get_collection_title($uri) {
		$col = $this->find_collection($uri);
		
		if ( $col === FALSE ) {
			return FALSE;
		}
		
		$title = $col->getElementsByTagNameNS("http://www.w3.org/2005/Atom","title");
		if ($title->length > 0) {
			return $title->item(0)->textContent;
		} else {
			return "";
		}
	}
	
	private function find_collection($uri) {
		if ( !isset($this->doc) ) {
			$this->doc = DOMDocument::load($this->filename);
		}
		$cols = $this->doc->getElementsByTagNameNS("http://www.w3.org/2007/app","collection");
		
		$urih = new App_Uri_Handler();
		$service_base = $this->base_uri;
		
		foreach ( $cols as $col ) {
			if ( $urih->resolve_uri($col->getAttributeNode("href"),$service_base) == $uri ) {
				return $col;
			}
		}
		
		return FALSE;
	}
}

?>
