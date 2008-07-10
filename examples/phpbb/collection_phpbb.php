<?php

require_once("../classes/phpbbliststore.php");
require_once("../classes/phpbbatomstore.php");
require_once("../classes/phpbb.php");

class App_Collection_phpbb extends App_Collection {

	protected $phpbb;
	protected $forum_id; 

	public function __construct($name, $service) {
		parent::__construct($name, $service);
		
		$phpbb = new phpbb_Forum("http://localhost/servertest/app/");
		
		$forum_id = 2;
		
		$this->list_store = new phpbb_list_store($phpbb, $forum_id, $this->base_uri);
		$this->atom_store = new phpbb_atom_store($phpbb, $forum_id, $this->base_uri);
		unset($this->feed_cache);
		
		$this->phpbb = $phpbb;
		$this->forum_id = $forum_id;
	}
	
	public function give_name($slug) {
		return $this->phpbb->next_post_id();
	}
}
