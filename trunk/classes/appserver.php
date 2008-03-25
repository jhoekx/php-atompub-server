<?php

require_once("appuri.php");
require_once("appuritemplate.php");

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
		
		$templates = array(
			array("App_Collection", "{colname}/"),
			array("App_Collection", "{colname}/pages/{nr}"),
			array("App_Entry", "{colname}/{entryname}"),
			array("App_Entry", "{colname}/{year}/{month}/{day}/{entryname}"),
			array("App_Servicedoc", "service"),
			array("App_Servicedoc", "")
		);
		
		foreach( $templates as $temp ) {
			$obj = $temp[0];
			$str = $temp[1];

			$template = new App_URITemplate($this->base_uri.$str);
			
			$res = $template->matches($uri);
			if ( $res !== FALSE ) {
				$resource = $this->create_resource($uri, $obj, $res);
				return $resource;
			}
		}
		
		throw new HTTPException("No matching resource!",404);
		
	}
	
	public function create_resource($uri, $obj, $vars) {
		switch ($obj) {
			case "App_Collection":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				$collection = $this->create_collection($vars["colname"], $service);
				$collection->uri = $uri;
				return $collection;
				break;
			case "App_Servicedoc":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				return $service;
				break;
			case "App_Entry":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				$collection = $this->create_collection($vars["colname"], $service);
				
				$entry = $collection->get_entry($uri);
				return $entry;
				break;
			default:
				throw new Exception("No resource!");
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
