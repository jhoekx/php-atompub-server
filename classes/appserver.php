<?php

require_once("appuri.php");
require_once("appuritemplate.php");

require_once("appservicedoc.php");
require_once("appcollection.php");
require_once("appcategory.php");

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
			array("App_Category", "-/{category}/"),
			array("App_Category", "-/{category}/pages/{nr}"),
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
				$collection = $this->create_collection($vars, $service);
				return $collection;
				break;
			case "App_Servicedoc":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				return $service;
				break;
			case "App_Entry":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				$collection = $this->create_collection($vars, $service);
				
				$entry = $collection->get_entry($uri);
				return $entry;
				break;
			case "App_Category":
				$service = new App_Servicedoc("service.xml", $this->base_uri);
				$category = new App_Category($uri, $this->store, $service);
				return $category;
				break;
			default:
				throw new Exception("No resource!");
		}
	}
	
	public function create_collection($vars, $service) {
		$name = $vars["colname"];
		
		if ( !array_key_exists("nr",$vars) ) {
			$uri = new URI($this->base_uri.$name."/");
		} else {
			$uri = new URI($this->base_uri.$name."/pages/".$vars["nr"]);
		}
	
		if ( file_exists("templates/collection_".$name.".php") ) {
			include_once("templates/collection_".$name.".php");
			$class = "App_Collection_".$name;
			$collection = new $class($uri, $this->store, $service);
		} else {
			$collection = new App_Collection($uri, $this->store, $service);
		}
		
		return $collection;
	}

}

?>
