<?php

class phpbb_list_store {

	private $phpbb;
	private $base_uri;

	public function __construct($phpbb, $forum_id, $base_uri) {
		$this->base_uri = $base_uri;
		$this->phpbb = $phpbb;
		$this->forum_id = $forum_id;
	}
	
	public function store($uri, $data) {
	}
	
	public function get($uri) {
		return $this->phpbb->get_listing($this->forum_id);
	}
	
	public function modified($uri) {
		return $this->phpbb->listing_last_modified($this->forum_id);
	}
	public function exists($uri) {
		return true;
	}
	
	public function remove($uri) {
	}

}
