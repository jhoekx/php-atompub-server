<?php

class App_Cleanup {

	private $id;
	private $uri;

	public function __construct($base_uri, $uri) {
		$this->uri = $uri;
	
		$parts = explode("/", str_replace($base_uri,"",$uri) );
		
		$domain = explode("/",str_replace("http://","",$base_uri));
		
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		
		$collection = $parts[0];
		$year = $parts[1];
		$month = $parts[2];
		$day = $parts[3];
		$name = $parts[4];
		
		$this->id = "tag:".$domain.",".$year."-".$month."-".$day.":".$collection."/".$name;
	}
	
	public function clean_entry($entry) {
		if ($entry->nodeType == XML_DOCUMENT_NODE) {
			$entry_doc = $entry;
		} else {
			$entry_doc = $entry->documentElement;
		}
		
		// Required fields:
		/* id, title, updated
		 * sometimes summary or content, but we don't require it.
		 */
		
		// id
		if ($entry_doc->getElementsByTagNameNS("http://www.w3.org/2005/Atom","id")->length == 0) {
			$id = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","id");
			$id->appendChild( $entry_doc->createTextNode($this->id) );
			
			$entry_doc->documentElement->appendChild($id);
		}
		
		// title
		if ($entry_doc->getElementsByTagNameNS("http://www.w3.org/2005/Atom","title")->length == 0) {
			$title = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","title");
			$title->appendChild( $entry_doc->createTextNode($this->id) );
			
			$entry_doc->documentElement->appendChild($title);
		}
		
		// updated
		if ($entry_doc->getElementsByTagNameNS("http://www.w3.org/2005/Atom","updated")->length == 0) {
			$updated = $entry_doc->createElementNS("http://www.w3.org/2005/Atom","updated");
			$updated->appendChild( $entry_doc->createTextNode(date(DATE_ATOM)) );
			
			$entry_doc->documentElement->appendChild($updated);
		}
		
	}
	
}

?>
