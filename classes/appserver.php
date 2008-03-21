<?php

require_once("appuri.php");

require_once("appservicedoc.php");
require_once("appcollection.php");

require_once("httpexception.php");

class App_Server {

	private $store;
	private $base_uri;

	public function __construct($store, $base_uri) {
		$this->store = $store;
		$this->base_uri = new URI($base_uri);
	}
	
	public function get_resource($uri) {

		$r_uri = $uri->base_on($this->base_uri);
		$path = $r_uri->components["path"];
		
		$parts = split("/",$path);
		switch ( count($parts) ) {
			case 1:
				switch ($parts[0]) {
					case "":
					case "service":
						$service = new App_Servicedoc("service.xml", $this->base_uri);
						return $service;
						break;
					default:
						throw new Exception("No resource.");
				}
				
				break;
			case 2:
				$name = $parts[0];
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				
				$collection = $this->create_collection($name, $service);
				
				if (strpos($path,".")===FALSE) {
					return $collection;
				} else {
					return $collection->get_entry($uri);
				}
				
			default:
				$colname = $parts[0];
				
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				$collection = $this->create_collection($colname, $service);
				
				
				$entry = $collection->get_entry($uri);
				
				return $entry;
				break;
		}
		
	}
	
	public function create_collection($name, $service) {
		if ( file_exists("templates/collection_".$name.".php") ) {
			include_once("templates/collection_".$name.".php");
			$class = "App_Collection_".$name;
			$collection = new $class($name, $this->store, $service);
		} else {
			$collection = new App_Collection($name, $this->store, $service);
		}
		
		return $collection;
	}

}

?>
