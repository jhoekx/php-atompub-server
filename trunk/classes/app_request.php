<?php

class App_Request {

	public $headers = array();
	
	public $method;
	public $request_uri;
	public $request_body;
	
	public function fill_from_server() {
		// Content-Type
		if ( array_key_exists("CONTENT_TYPE",$_SERVER) ) {
			$this->headers["Content-Type"] = $_SERVER["CONTENT_TYPE"];
		}
		// Slug
		if ( array_key_exists("HTTP_SLUG",$_SERVER) ) {
			$this->headers["Slug"] = $_SERVER["HTTP_SLUG"];
		}
		
		// If-none-match
		if ( array_key_exists("HTTP_IF_NONE_MATCH",$_SERVER) ) {
			$this->headers["If-None-Match"] = $_SERVER["HTTP_IF_NONE_MATCH"];
		}
		// If-Match
		if ( array_key_exists("HTTP_IF_MATCH",$_SERVER) ) {
			$this->headers["If-Match"] = $_SERVER["HTTP_IF_MATCH"];
		}
		// If-Modified-Since
		if ( array_key_exists("HTTP_IF_MODIFIED_SINCE",$_SERVER) ) {
			$this->headers["If-Modified-Since"] = $_SERVER["HTTP_IF_MODIFIED_SINCE"];
		}
		
		// Accept-Encoding
		if ( array_key_exists("HTTP_ACCEPT_ENCODING",$_SERVER) ) {
			$this->headers["Accept-Encoding"] = $_SERVER["HTTP_ACCEPT_ENCODING"];
		}
		
		// Request-URI
		if ( array_key_exists("REQUEST_URI",$_SERVER) ) {
			$this->request_uri = $_SERVER["REQUEST_URI"];			
		}
		// Request
		$this->method = $_SERVER["REQUEST_METHOD"];
	
		if ($this->method == "POST" || $this->method == "PUT") {
			$this->request_body = file_get_contents("php://input");
		}
	}
}

?>