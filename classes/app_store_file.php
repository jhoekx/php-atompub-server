<?php

require_once("app_store.php");

// File based version
class App_Store_File implements App_Store, App_Categories_Store{
	
	public $store_dir;
	private $base_uri;
	public $lastEdited;
	private $entries_per_page;
	
	
	public function __construct($dir, $base_uri, $service) {
		$this->store_dir = $dir;
		
		if ( !file_exists($dir) ) {
			mkdir($dir);
		}
		
		$this->base_uri = $base_uri;
		$this->entries_per_page = 10;
		$this->service = $service;
	}

	public function get_collection($name) {
		return $this->get_collection_page($name, 0);
	}
	
	public function get_collection_page($name, $page) {
		// check if collection exists, otherwise, create
		if ( !$this->collection_exists($name) ) {
			$this->update_collection($name);
		}
		
		// return specific page
		if ( !file_exists("$this->store_dir/$name/pages/$page.atom") ) {
			$entries  = $this->get_collection_list($name);
			$data = $this->create_feed_page($name, $page, $entries);
			file_put_contents("$this->store_dir/$name/pages/$page.atom",$data);
			
			return $data;
		} else {
			return file_get_contents("$this->store_dir/$name/pages/$page.atom");
		}
	}
	public function collection_last_edited($name) {
		if ( $this->collection_exists($name) ) {
			return filemtime("$this->store_dir/$name/pages/1.atom");
		} else {
			return time();
		}
	}
	
	public function collection_exists($name) {
		return file_exists("$this->store_dir/$name/pages/1.atom");
	}
	
	public function resource_exists($collection, $year, $month, $day, $name) {
		$name = rawurldecode($name);
		if ( file_exists("$this->store_dir/$collection/$year/$month/$day/$name") ) {
			$this->lastEdited = filemtime("$this->store_dir/$collection/$year/$month/$day/$name");
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function store_resource($collection, $year, $month, $day, $name, $contents) {
		
		if ( !file_exists("$this->store_dir/$collection/") ) {
			mkdir("$this->store_dir/$collection");
		}
		
		if ( !file_exists("$this->store_dir/$collection/$year/") ) {
			mkdir("$this->store_dir/$collection/$year");
		}
		
		if ( !file_exists("$this->store_dir/$collection/$year/$month/") ) {
			mkdir("$this->store_dir/$collection/$year/$month");
		}
		
		if ( !file_exists("$this->store_dir/$collection/$year/$month/$day/") ) {
			mkdir("$this->store_dir/$collection/$year/$month/$day");
		}
		
		$name = rawurldecode($name);
		file_put_contents("$this->store_dir/$collection/$year/$month/$day/$name", $contents);
		
		$this->update_collection($collection);
		
		return TRUE;
	}
	
	public function get_resource($collection, $year, $month, $day, $name) {
		$name = rawurldecode($name);
		if ( file_exists("$this->store_dir/$collection/$year/$month/$day/$name") ) {
			return file_get_contents("$this->store_dir/$collection/$year/$month/$day/$name");
		} else {
			return NULL;
		}
	}
	
	public function remove_resource($collection, $year, $month, $day, $name) {
		$name = rawurldecode($name);
		if ( file_exists("$this->store_dir/$collection/$year/$month/$day/$name") ) {
			$ret = unlink("$this->store_dir/$collection/$year/$month/$day/$name");
			
			$this->update_collection($collection);
			
			return $ret;
		} else {
			return NULL;
		}		
	}

	
	public function get_category_page($name, $page) {
		if ( !file_exists($this->store_dir."/cats/".$name.".json") ) {
			return "";
		}
	
		// return specific page
		if ( !file_exists("$this->store_dir/cats/$name/$page.atom") ) {
			$entries  = $this->get_category($name);
			usort($entries,"entries_sort"); // flatten
			$data = $this->create_feed_page($name, $page, $entries);
			file_put_contents("$this->store_dir/cats/$name/$page.atom",$data);
			
			return $data;
		} else {
			return file_get_contents("$this->store_dir/cats/$name/$page.atom");
		}
	}
	
	public function category_last_edited($name) {
		if ( file_exists("$this->store_dir/cats/$name/1.atom") ) {
			return filemtime("$this->store_dir/cats/$name/1.atom");
		} else {
			return time();
		}
	}
	public function add_to_category($cat, $uri, $time) {
		$list = $this->get_category($cat);
		
		$list[$uri] = array("Edit"=>$time, "Item"=>$uri);
		
		$this->save_category($cat, $list);
	}
	
	public function remove_from_category($cat, $uri) {
		$list = $this->get_category($cat);
		
		if ( array_key_exists($uri, $list) ) {
			unset($list[$uri]);
		}
		
		$this->save_category($cat, $list);
	}
	
	// update a collection
	// deletes all pages, creates the first one
	private function update_collection($name) {
	
		// create a dir for the collection
		if ( !file_exists("$this->store_dir/$name/") ) {
			mkdir("$this->store_dir/$name");
		}
		
		$this->rm_pages_dir("$this->store_dir/$name/pages");
		mkdir($this->store_dir."/".$name."/pages");
		
		// create a new feed
		$entries  = $this->get_collection_list($name);
		$data= $this->create_feed_page($name, 1, $entries);
		file_put_contents("$this->store_dir/$name/pages/1.atom",$data);
	}
	
	// Remove the old feed cache
	private function rm_pages_dir($dir) {
		
		if ( ! file_exists($dir) ) {
			return;
		}
		
		$d = dir($dir);
		
		while (false !== ($file = $d->read())) {
			if ($file != "." && $file != "..") {
				unlink($dir."/".$file);
			}
		}
		$d->close();
		
		rmdir($dir);
	}
	
	/**
	 * Create one page of a paged feed.
	 *
	 * @param string $name The name of the collection.
	 * @param int $page The page in the feed.
	 * @param array $list The list of entries.
	 *
	 * @return string The contents of the feed.
	 */
	private function create_feed_page($name, $page, $entries) {
		
		$feed = $this->create_feed($name);
		
		$total_entries = count($entries);
		
		$nr_pages = (int)($total_entries/$this->entries_per_page);
		if ( $total_entries % $this->entries_per_page != 0 ) {
			$nr_pages = $nr_pages + 1;
		}
		
		if ( $page > $nr_pages && $nr_pages != 0) {
			$data = "";
		}  else {
		
			// self link
			$feed->getElementsByTagNameNS("http://www.w3.org/2005/Atom","link")->item(0)
					->setAttribute("href",$this->base_uri.$name."/".$page);
			
			// Other links
			if ($page != 0 ) {
				// next
				if ($page < $total_entries/$this->entries_per_page) {
					$next = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
					$next->setAttribute("rel","next");
					$next->setAttribute("href",$this->base_uri.$name."/".($page+1));
					$feed->documentElement->appendChild($next);
				}
				// previous
				if ($page > 1) {
					$prev = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
					$prev->setAttribute("rel","previous");
					$prev->setAttribute("href",$this->base_uri.$name."/".($page-1));
					$feed->documentElement->appendChild($prev);
				}
				// first
				$first = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
				$first->setAttribute("rel","first");
				$first->setAttribute("href",$this->base_uri.$name."/1");
				$feed->documentElement->appendChild($first);
				// last
				$last = $feed->createElementNS("http://www.w3.org/2005/Atom","link");
				$last->setAttribute("rel","last");
				$last->setAttribute("href",$this->base_uri.$name."/".$nr_pages);
				$feed->documentElement->appendChild($last);
			}
			
			// $page = 0 -> full collection
			if ($page == 0 )  {
				$stop = $total_entries;
				$start = 0;
				
				// complete
				$complete = $feed->createElementNS("http://purl.org/syndication/history/1.0",
													"fh:complete");
				$feed->documentElement->appendChild($complete);
				
			} else {
				$start = $this->entries_per_page*($page-1);
				$stop = $this->entries_per_page*($page-1) + $this->entries_per_page;		
				if ($stop>$total_entries) {
					$stop = $total_entries;
				}	
			}
			
			for ( $i=$start; $i<$stop; $i++ ) {
				if ( array_key_exists($i, $entries) ) {
					$entry = $entries[$i];
					
					$entry_doc = DOMDocument::load($entry["Item"]);
					$last_edited = $entry["Edit"];
					
					// last edited
					$edit = $entry_doc->createElementNS("http://www.w3.org/2007/app","app:edited");
					$edit->appendChild( $entry_doc->createTextNode(date(DATE_ATOM,$last_edited)) );
					$entry_doc->documentElement->appendChild($edit);
					
					$entry_el = $feed->importNode($entry_doc->documentElement, true);
					$feed->documentElement->appendChild($entry_el);
				}
			}
			$data = $feed->saveXML();
			
		}
		
		return $data;
	}
	
	// return a sorted list of items in a collection
	// format: [ {Item:path, Edit:int}, ... ]
	private function get_collection_list($name) {
	
		if ( !file_exists("$this->store_dir/$name/pages/list.json") ) {
			$entries = $this->walk_dir("$this->store_dir/$name/");
			
			usort($entries,"entries_sort");
			
			// save entries
			file_put_contents("$this->store_dir/$name/pages/list.json", json_encode($entries) );
		} else {
			$entries = json_decode(file_get_contents("$this->store_dir/$name/pages/list.json"), TRUE );
		}
		return $entries;
	}
	
	// create a new feed, self link not yet set
	private function create_feed($name) {
		$feed_template = '<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
<title/>
<id/>
<updated/>
<link rel="self"/>
</feed>';

		$domain = explode("/", str_replace("http://","",$this->base_uri));
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		$id = "tag:".$domain.",2007:$name";
		$title = $this->service->get_collection_title($this->base_uri.$name."/");
		if ($title == "" || $title == FALSE) {
			$title = "$domain $name";
		}
		
		// load
		$feed = DOMDocument::loadXML($feed_template);

		// required elements
		$feed->getElementsByTagName("title")->item(0)->appendChild( 
				$feed->createTextNode( htmlspecialchars($title) ) );
		$feed->getElementsByTagName("id")->item(0)->appendChild( 
				$feed->createTextNode($id) );
		$feed->getElementsByTagName("updated")->item(0)->appendChild( 
				$feed->createTextNode( date(DATE_ATOM) ) );
		
		return $feed;
	}
	
	// returns an array with all entries in a directory
	// format: [ {Item:path, Edit:int}, ... ]
	private function walk_dir($dir) {
		$contents = array();
		
		$d = dir($dir);
		
		while (false !== ($file = $d->read())) {
			if ($file != "." && $file != "..") {
				if ( is_dir($dir.$file) ) {
					$contents = array_merge($contents, $this->walk_dir($dir.$file."/") );
				} else {
					$extension = explode(".",$file);
					$extension = $extension[count($extension)-1];
					
					if ($extension == "atomentry") {
						$edit = (string)filemtime($dir.$file);
						$new = array( array("Item" => $dir.$file, "Edit" => $edit) );
						
						$contents = array_merge($contents, $new);
					}
				}
			}
		}
		$d->close();
		
		return $contents;
	}
	
	private function get_category($name) {
		if ( file_exists($this->store_dir."/cats/".$name.".json") ) {
			return json_decode(file_get_contents($this->store_dir."/cats/".$name.".json"),TRUE);;
		} else {
			return json_decode("[]",TRUE);
		}
	}
	
	private function save_category($name, $list) {
		if ( !file_exists($this->store_dir."/cats/") ) {
			mkdir($this->store_dir."/cats/");
		}
		
		$this->rm_pages_dir("$this->store_dir/cats/$name/");
		if ( !file_exists($this->store_dir."/cats/$name/") ) {
			mkdir($this->store_dir."/cats/".$name);
		}

		file_put_contents($this->store_dir."/cats/".$name.".json", json_encode($list));
	}

}

function entries_sort($a, $b) {
	return $b["Edit"]-$a["Edit"];
}
?>
