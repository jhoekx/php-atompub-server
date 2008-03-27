<?php


class App_Categorized_Collection extends App_Collection {
	
	protected $temp_cats;
	
	public function __construct($name, $store, $service) {
		parent::__construct($name, $store, $service);
		
		$this->addEventListener("entry_create", $this, "add_category");
		$this->addEventListener("before_entry_update", $this, "temp_category");
		$this->addEventListener("entry_update", $this, "update_category");
		$this->addEventListener("entry_remove", $this, "remove_category");
	}
	
	public function temp_category($event) {
		$entry = $event->entry;
		$this->temp_cats = $this->find_categories($entry->get_document()->documentElement);
	}
	
	public function update_category($event) {
		$entry = $event->entry;
		
		$cats = $this->find_categories($entry->get_document()->documentElement);
		
		if ( !$this->entry_allowed($cats) ) {
			throw new HTTPException("Category not allowed", 412);
		}
		
		$oldcats = array_diff($this->temp_cats, $cats);
		$newcats = array_diff($cats, $this->temp_cats);
		$upcats = array_diff($cats, $newcats);
		
		foreach( $oldcats as $cat ) {
			$category = new App_Category($cat, $this->store, $this->service);
			$category->remove_entry($entry);
		}
		
		foreach( $newcats as $cat ) {
			$category = new App_Category($cat, $this->store, $this->service);
			$category->add_entry($entry);
		}
		
		foreach( $upcats as $cat ) {
			$category = new App_Category($cat, $this->store, $this->service);
			$category->update_entry($entry);
		}
	}
	
	public function remove_category($event) {
		$entry = $event->entry;
		
		$cats = $this->find_categories($entry->get_document()->documentElement);
		
		foreach( $cats as $cat ) {
			$category = new App_Category($cat, $this->store, $this->service);
			$category->remove_entry($entry);
		}
	}
	
	public function add_category($event) {
		$entry = $event->entry;
		
		$cats = $this->find_categories($entry->get_document()->documentElement);
		
		if ( !$this->entry_allowed($cats) ) {
			throw new HTTPException("Category not allowed", 412);
		}
		
		foreach( $cats as $cat ) {
			$category = new App_Category($cat, $this->store, $this->service);
			$category->add_entry($entry);
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
	
	private function entry_allowed($cats) {
		if ( count($cats) == 0 ) {
			return TRUE;
		}
	
		foreach( $cats as $cat ) {
			if ( $this->service->category_allowed($cat, $this->feed_uri) ) {
				return TRUE;
			}
		}
		
		return FALSE;
	}
}
