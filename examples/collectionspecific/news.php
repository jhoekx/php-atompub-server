<?php
require_once("../classes/app_collection_specific.php");
require_once("htmlpurifier/HTMLPurifier.auto.php");

class news_specific implements App_Collection_Specific{
	public function on_read($response) {
	
	}
	
	public function on_create($entry) {
		// add html link rel
		$children = $entry->documentElement->childNodes;
		foreach ($children as $child) {
			if( $child->nodeType == XML_ELEMENT_NODE && $child->localName == "link"
					&& $child->namespaceURI == "http://www.w3.org/2005/Atom") {
						// edit
						if ( strcasecmp(trim($child->getAttribute("rel")), "edit") == 0) {
							$edit = $child;
						}
			}
		}

		// alternate link
		if ( !isset($edit)) {
			return;
		}
		// alternate location
		$location = $edit->getAttribute("href");
		$new_uri = str_replace("app/","",$location);
		$new_uri = str_replace(".atomentry","",$new_uri);
		
		$alternate = $entry->createElementNS("http://www.w3.org/2005/Atom","link");
		$alternate->setAttribute("rel","alternate");
		$alternate->setAttribute("type","text/html");
		$alternate->setAttribute("href",$new_uri);
		
		$entry->documentElement->appendChild($alternate);
		
		// published
		if ($entry->getElementsByTagNameNS("http://www.w3.org/2005/Atom","published")->length == 0) {
			$created = $entry->createElementNS("http://www.w3.org/2005/Atom","published");
			$created->appendChild( $entry->createTextNode(date(DATE_ATOM)) );
			
			$entry->documentElement->appendChild($created);
		}
		
		// author
		if ($entry->getElementsByTagNameNS("http://www.w3.org/2005/Atom","author")->length == 0) {
			$author = $entry->createElementNS("http://www.w3.org/2005/Atom","author");
			$author_name = $entry->createElementNS("http://www.w3.org/2005/Atom","name");
			
			$author_name->appendChild( $entry->createTextNode("Me") );
			$author->appendChild($author_name);
			$entry->documentElement->appendChild($author);
		}
		
		$this->clean_content($entry);

	}
	
	public function on_update($entry) {
		$this->clean_content($entry);
	}
	
	public function on_delete($entry) {
	
	}
	
	private function clean_content($entry) {
		$config = HTMLPurifier_Config::createDefault();

		$config->set('Core', 'Encoding', 'UTF-8');
		$config->set('HTML', 'Doctype', 'XHTML 1.0 Strict');
		
		$purifier = new HTMLPurifier($config);
		
		$contents = $entry->getElementsByTagNameNS("http://www.w3.org/2005/Atom","content");
		$summaries = $entry->getElementsByTagNameNS("http://www.w3.org/2005/Atom","summary");
		
		foreach( $contents as $content) {
			$this->clean_element($content,$entry,$purifier);
		}
		foreach( $summaries as $summary) {
			$this->clean_element($summary,$entry,$purifier);
		}
	}
	
	private function clean_element($element, $entry, $purifier) {
			
		// xhtml
		if ( $element->hasAttribute("type") && $element->getAttribute("type") == "xhtml" ) {
			// assume div
			$div = $element->getElementsByTagNameNS("http://www.w3.org/1999/xhtml","div");
			if ($div->length != 0) {
				$div=$div->item(0);
				
				$new_html = $purifier->purify($entry->saveXML($div));
				
				$new_html = "<div xmlns=\"http://www.w3.org/1999/xhtml\">".substr($new_html,5);
				
				$new_doc = DOMDocument::loadXML($new_html);
				
				$new_div = $entry->importNode($new_doc->documentElement,true);
				$div->parentNode->replaceChild($new_div, $div);
			}
		}
		
		// html
		if ( $element->hasAttribute("type") && $element->getAttribute("type") == "html" ) {
			$text = htmlspecialchars_decode($element->textContent);
			
			$new_html = $purifier->purify($text);
			$new_html = "<div xmlns=\"http://www.w3.org/1999/xhtml\">".$new_html."</div>";
			$new_doc = DOMDocument::loadXML($new_html);
			
			$new_div = $entry->importNode($new_doc->documentElement,true);
			while ($element->firstChild) {
				$element->removeChild($element->firstChild);
			}
			$element->appendChild($new_div);
			
			$element->setAttribute("type","xhtml");
		}
		
		// text
		if ( (!$element->hasAttribute("type")) || 
			 ($element->hasAttribute("type") && $element->getAttribute("type") == "text") ) {
			$text = $element->textContent;
			
			$new_html = $purifier->purify($text);
			$new_html = "<div xmlns=\"http://www.w3.org/1999/xhtml\">".$new_html."</div>";
			$new_doc = DOMDocument::loadXML($new_html);
			
			$new_div = $entry->importNode($new_doc->documentElement,true);
			while ($element->firstChild) {
				$element->removeChild($element->firstChild);
			}
			$element->appendChild($new_div);
			
			$element->setAttribute("type","xhtml");
		}
	}
	
}

?>