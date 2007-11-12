<?php

class App_Mime {
	
	private $extensions = array(
		"atomentry" => "application/atom+xml;type=entry",
		"atom" => "application/atom+xml;type=feed",
		"html" => "text/html",
		"xhtml" => "application/xhtml+xml",
		"xml" => "application/xml",
		"txt" => "text/plain",
		"jpg" => "image/jpeg",
		"gif" => "image/gif",
		"png" => "image/png",
		"pdf" => "application/pdf",
		"svg" => "application/svg+xml",
		"xslt" => "application/xslt+xml");
	
	
	public function is_atom_entry($doc) {
		$cA = ($doc->documentElement->localName == "entry");
		$cB = ($doc->documentElement->namespaceURI == "http://www.w3.org/2005/Atom");
		return $cA && $cB;
	}
	
	public function is_media_link($doc) {
		$links = $doc->documentElement->childNodes;
		
		foreach ( $links as $link ) {
			if( $link->nodeType == XML_ELEMENT_NODE 
						&& $link->localName == "link"
						&& $link->namespaceURI == "http://www.w3.org/2005/Atom") {
				if ( strcasecmp(trim($link->getAttribute("rel")), "edit-media") == 0) {
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	public function get_mimetype($name) {
		$exploded_name = explode(".",$name);
		$ext = $exploded_name[count($exploded_name)-1];
		
		if ( isset($this->extensions[$ext]) ) {
			return $this->extensions[$ext];
		} else {
			return "text/plain";
		}
	}
	
	public function get_extension($mimetype) {
		$mimetest = strtolower(str_replace(" ","",$mimetype));
		
		$extensions = array_flip($this->extensions);
		
		if ($mimetest == "application/atom+xml") {
			return "atom";
		} else if ( isset($extensions[$mimetest] ) ) {
			return $extensions[$mimetest];
		} else {
			return "txt";
		}
	}
	
	public function get_charset($content_type) {
		
	}
	
}

?>