<?php

require_once("atomfeed.php");

class App_Category extends Atom_Feed {

	public function __construct($uri, $store, $service) {
		if ( is_string($uri) ) { // name
			$name = $uri;
			$nuri = new URI("-/".$name."/");
			$uri = $nuri->resolve($service->base_uri);
		}
	
		parent::__construct($uri, $store, $service);
	}
	
	protected function set_name_and_pagenr() {
		$page_template = new App_URITemplate($this->base_uri."-/{name}/pages/{pagenr}");
		$name_template = new App_URITemplate($this->base_uri."-/{name}/");

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
	
	public function get_collection_page($pagenr) {
		
		$key = $this->get_page_key($pagenr);
		if ( !$this->store->exists($key) ) {
			$doc = $this->create_page($pagenr);
			
			$fs = new FeedSerializer();
			$data = $fs->writeToString($doc);
			
			$this->store->store($key, $data);
			
			return $data;
		}
		
		return $this->store->get($key);
	}
	
	public function add_entry($entry) {
		$list = $this->get_collection_list();
		
		array_push($list, array("URI"=>$entry->uri->to_string(), "Edit"=>time()) );
		
		$this->save_collection_list($list);
		$this->update_pages();
		
	}
	
	public function update_entry($entry) {
		$list = $this->get_collection_list();
		
		// find entry
		for ($i=0; $i<count($list); $i++) {
			if ($list[$i]["URI"] == $entry->uri->to_string() ) {
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
	
	public function remove_entry($entry) {
		$list = $this->get_collection_list();
		
		for ($i=0; $i<count($list); $i++) {
			if ($list[$i]["URI"] == $entry->uri->to_string() ) {
				$index = $i;
			}
		}
		
		if (isset($index)) {
			array_splice($list,$index,1);
		}
		
		$this->save_collection_list($list);
		$this->update_pages();
	}
	
	protected function base_name() {
		return $this->base_uri."-/".$this->name."/";
	}
}
