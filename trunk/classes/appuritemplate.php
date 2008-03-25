<?php

class App_URITemplate {
	
	private $expr;
	
	public function __construct($expr) {
		$this->expr = $expr;
	}
	
	public function matches($uri) {
		$parts = split("/", $this->expr);
		$uriparts = split("/", $uri);
		
		if ( count($parts) != count($uriparts) ) {
			return FALSE;
		}
		
		$matches = array();
		for ($i=0; $i<count($parts); $i++) {
			if ( strpos($parts[$i], "{") !== FALSE ) { // variable
				$varname = substr($parts[$i],1,strlen($parts[$i])-2);
				
				$matches[$varname] = $uriparts[$i];
			} else {
				if ( $parts[$i] != $uriparts[$i] ) {
					return FALSE;
				}
			}
		}
		return $matches;
	}
	
}