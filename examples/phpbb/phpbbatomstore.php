<?php

class phpbb_atom_store {

	private $phpbb;
	private $base_uri;
	private $forum_id;

	public function __construct($phpbb, $forum_id, $base_uri) {
		$this->base_uri = $base_uri;
		$this->phpbb = $phpbb;
		$this->forum_id = $forum_id;
	}
	
	public function store($uri, $data) {
		$id = $this->get_key($uri);
		$this->phpbb->save_post($this->forum_id, $id, $data);
	}
	
	public function get($uri) {
		$id = $this->get_key($uri);
		
		return $this->phpbb->get_post($id)->saveXML();
	}
	
	public function modified($uri) {
		$id = $this->get_key($uri);
		
		return $this->phpbb->post_last_modified($id);
	}
	public function exists($uri) {
		$id = $this->get_key($uri);
		
		return $this->phpbb->post_exists($id);
	}
	
	public function remove($uri) {
		$id = $this->get_key($uri);
		
		$this->phpbb->remove_post($id);
	}
	
	private function get_key($uri) {
		$uri = new URI($uri);
		$parts = split("/",$uri);
		$id_part = $parts[count($parts)-1];
		$id = str_replace(".atomentry","",$id_part);
		
		return $id;
	}

}
