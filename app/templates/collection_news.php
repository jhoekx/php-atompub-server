<?php

/**
 * App_Collection_news demonstrates how to extend a collection.
 *  - It gives a custom Slug to POSTed entries (yyyy/mm/dd/name)
 *  - An atom:link element is added to point to the related HTML document
 *  - Content is sanitized using HTMLPurifier
 *
 * Note that you should download HTMLPurifier from http://htmlpurifier.org/ 
 * and install it in "./purifier/". Any POST to this collection will fail 
 * without it. Or install it using PEAR and change the require to :
 *     require_once("HTMLPurifier.auto.php");
 */
class App_Collection_news extends App_Collection {

	public function __construct($name, $service) {
		parent::__construct($name, $service);
		
		$this->addEventListener("entry_create", $this, "on_entry_create");
	}
	
	public function give_name($slug) {
		return date("Y")."/".date("m")."/".date("d")."/".$slug;
	}
	
	public function on_entry_create($event) {
		$entry = $event->entry;
		
		$doc = $entry->get_document();
		
		// Link to the related HTML document
		$link = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
		$link->setAttribute("rel","alternate");
		$link->setAttribute("type","text/html");
		
		// Change the URI to the correct value, this is just an example.
		$uri = new URI("http://localhost/appv2/news/".$entry->name);
		$link->setAttribute("href", $uri);
		
		$doc->documentElement->appendChild($link);
		
		require_once("purifier/HTMLPurifier.auto.php");
		
		$this->clean_content($doc);
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
