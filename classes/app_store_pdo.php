<?php

require_once("app_mime.php");

// PDO based version
class App_Store {
	
	private $dbh;
	private $base_uri;
	public $lastEdited;
	
	public function __construct($dbh, $base_uri) {
		$this->base_uri = $base_uri;
	
		$this->dbh = $dbh;
		
		$this->dbh->query("CREATE TABLE IF NOT EXISTS app_store (
							collection VARCHAR(255),
							media VARCHAR(1) /*0: atomentry, 1:medialink, 2:media*/ ,
							lastEdited VARCHAR(16),
							year VARCHAR(4),
							month VARCHAR(2),
							day VARCHAR(2),
							name VARCHAR(255),
							content BLOB )") or die("Error creating table");
							
		$this->dbh->query("CREATE TABLE IF NOT EXISTS app_collection_etag (
							collection VARCHAR(255),
							lastEdited VARCHAR(16))");
	}

	/* Returns a String containing the whole collection */
	public function get_collection($name) {
		return $this->get_collection_page($name, 0);
	}
	
	/* Returns a String containing the specific page of a collection */
	public function get_collection_page($name, $page) {
		
		$entries_per_page = 10;
		
		$sql = "SELECT * FROM app_store WHERE (
											collection='$name' AND
											(media='0' OR media='1'))
										ORDER BY lastEdited DESC";
		$ress = $this->dbh->query($sql)->fetchAll();
		$total_entries = count($ress);
		
		$nr_pages = (int)($total_entries/$entries_per_page);
		if ( $total_entries % $entries_per_page != 0 ) {
			$nr_pages = $nr_pages + 1;
		}
		if ( $page > $nr_pages ) {
			return "";
		}
		
		$doc = new DOMDocument("1.0","utf-8");

		$domain = explode("/", str_replace("http://","",$this->base_uri));
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		$id = "tag:".$domain.",2007:$name";
		$title = "$domain $name";
		
		// FEED
		$doc_el = $doc->createElementNS("http://www.w3.org/2005/Atom","feed");
		$doc->appendChild($doc_el);
		
		// TITLE
		$title_el = $doc->createElementNS("http://www.w3.org/2005/Atom","title");
		$title_el->appendChild( $doc->createTextNode($title) );
		$doc_el->appendChild($title_el);
		
		// ID
		$id_el = $doc->createElementNS("http://www.w3.org/2005/Atom","id");
		$id_el->appendChild( $doc->createTextNode($id) );
		$doc_el->appendChild($id_el);
		
		// updated
		$updated = $doc->createElementNS("http://www.w3.org/2005/Atom","updated");
		$updated->appendChild( $doc->createTextNode(date(DATE_ATOM,(int)$this->collection_last_edited($name))) );
		$doc_el->appendChild($updated);
		
		// self
		$self = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
		$self->setAttribute("rel","self");
		if ($page != 0) {
			$self->setAttribute("href",$this->base_uri.$name."/".$page);
		}
		$doc_el->appendChild($self);
		
		if ($page != 0 ) {
			// next
			if ($page < $total_entries/$entries_per_page) {
				$next = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
				$next->setAttribute("rel","next");
				$next->setAttribute("href",$this->base_uri.$name."/".($page+1));
				$doc_el->appendChild($next);
			}
			// previous
			if ($page > 1) {
				$prev = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
				$prev->setAttribute("rel","previous");
				$prev->setAttribute("href",$this->base_uri.$name."/".($page-1));
				$doc_el->appendChild($prev);
			}
			// first
			$first = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
			$first->setAttribute("rel","first");
			$first->setAttribute("href",$this->base_uri.$name."/1");
			$doc_el->appendChild($first);
			// last
			$last = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
			$last->setAttribute("rel","last");
			
			$last_value = (int)($total_entries/$entries_per_page);
			if ( $total_entries % $entries_per_page != 0 ) {
				$last_value = $last_value+1;
			}
			
			$last->setAttribute("href",$this->base_uri.$name."/".$last_value);
			$doc_el->appendChild($last);
		} else  {
			// $page = 0 -> full collection
			$entries_per_page = $total_entries;
			$page = 1;
			
			// complete
			$complete = $feed->createElementNS("http://purl.org/syndication/history/1.0",
												"fh:complete");
			$feed->documentElement->appendChild($complete);
		}
		
		$first = TRUE;
		$stop = $entries_per_page*($page-1) + $entries_per_page;
		if ($stop>$total_entries) {
			$stop = $total_entries;
		}
		for ( $i=$entries_per_page*($page-1); $i < $stop; $i++) {
			$res = $ress[$i];
			
			$res_doc = DOMDocument::loadXML($res["content"]);
			
			// last edited
			$edit = $res_doc->createElementNS("http://www.w3.org/2007/app","app:edited");
			$edit->appendChild( $res_doc->createTextNode(date(DATE_ATOM,(int)$res["lastEdited"])) );
			if ( $first == TRUE ) {
				$this->lastEdited = $res["lastEdited"];
				$first = FALSE;
			}
			$res_doc->documentElement->appendChild($edit);
			
			$collection = $res['collection'];
			$year = $res['year'];
			$month = $res['month'];
			$day = $res['day'];
			$name = $res['name'];
			
			// add to collection
			$entry = $doc->importNode($res_doc->documentElement, TRUE);
			$doc_el->appendChild($entry);
		}
		
		$doc->normalizeDocument();
		return $doc->saveXML();	
	}
	
	
	public function collection_last_edited($name) {
		$sql = "SELECT (lastEdited) FROM app_collection_etag WHERE (
											collection='$name')
										LIMIT 1";
		$res = $this->dbh->query($sql)->fetch();
								
		return $res['lastEdited'];
	}
	private function collection_edited($name) {
		$edited = time();
		
		$testsql =  "SELECT (lastEdited) FROM app_collection_etag WHERE (
											collection='$name')
										LIMIT 1";
		$testres = $this->dbh->query($testsql)->fetchAll();
		if ( count($testres) > 0 ) {
			$sql = "UPDATE app_collection_etag
						SET lastEdited='$edited'
						WHERE (collection='$name')";
			$this->dbh->query($sql);
		} else {
			$sql = "INSERT INTO app_collection_etag
						(collection, lastEdited)
						VALUES ('$name', '$edited')";
			$this->dbh->query($sql);
		}
	}

	public function collection_exists($name) {
		$sql = "SELECT (lastEdited) FROM app_store WHERE (
											collection='$name')
										LIMIT 1";
		$res = $this->dbh->query($sql)->fetchAll();
		
		if (count($res) > 0) {
			return TRUE;
		} else {
			
			return TRUE;
		}
	}
	
	/* Checks if a given resource exists, returns BOOLEAN */
	public function resource_exists($collection, $year, $month, $day, $name) {
		
		$sql = "SELECT (lastEdited) FROM app_store WHERE (
									collection='$collection' AND
									year='$year' AND
									month='$month' AND
									day='$day' AND
									name=:name)";
									
		$stmt = $this->dbh->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":name", $name);
		
		$stmt->execute();
		$res = $stmt->fetchAll();
		
		if (count($res) > 0 ) {
			$this->lastEdited = $res[0]['lastEdited'];
		}
		return (count($res) > 0);
	}
	
	/* Adds a resource to a collection, overwrites a previous version*/
	public function store_resource($collection, $year, $month, $day, $name, $contents) {
		
		// current date
		$lastEdited = time();
		$this->collection_edited($collection);
		
		// get media type
		$amime = new App_Mime();
		$entry = DOMDocument::loadXML($contents, LIBXML_NOWARNING+LIBXML_NOERROR);
		
		if ($entry != FALSE && $amime->is_atom_entry($entry) && 
				$amime->get_mimetype($name) == "application/atom+xml;type=entry") {
			if( $amime->is_media_link($entry) ) {
				$media = 1;
			} else {
				$media = 0;
			}
		} else {
			$media = 2;
		}

		
		if ( $this->resource_exists($collection, $year, $month, $day, $name) ) {
			// UPDATE
			// TODO: check if same media type
			$stmt = $this->dbh->prepare("UPDATE app_store 
											SET 
												content=:content, 
												lastEdited='$lastEdited'
											WHERE (
												collection='$collection' AND
												year='$year' AND
												month='$month' AND
												day='$day' AND
												name=:name)");
			$stmt->bindParam(":name", $name);
			$stmt->bindParam(":content",$contents);
			
			$stmt->execute();
		} else {
			// NEW RESOURCE
			$stmt = $this->dbh->prepare("INSERT INTO app_store (
											collection,
											media,
											lastEdited,
											year,
											month,
											day,
											name,
											content
										)
										VALUES (
											'$collection',
											'$media',
											'$lastEdited',
											'$year',
											'$month',
											'$day',
											:name,
											:content
										)");
			$stmt->bindParam(":name", $name);
			$stmt->bindParam(":content", $contents);
			
			$stmt->execute();
		}
	}
	
	/* Fetch a resource from a collection, returns a STRING */
	public function get_resource($collection, $year, $month, $day, $name) {
		$stmt = $this->dbh->prepare("SELECT (content) FROM app_store WHERE (
									collection='$collection' AND
									year='$year' AND
									month='$month' AND
									day='$day' AND
									name=:name)");
		$stmt->bindParam(":name", $name);
		
		$stmt->execute();
		
		$row = $stmt->fetch();
		return $row['content'];
	}
	
	/* Removes a resource from a collection */
	public function remove_resource($collection, $year, $month, $day, $name) {
		$stmt = $this->dbh->prepare("DELETE FROM app_store WHERE (
									collection='$collection' AND
									year='$year' AND
									month='$month' AND
									day='$day' AND
									name=:name)");
		$stmt->bindParam(":name", $name);
		
		$stmt->execute();
		
		$this->collection_edited($collection);
	}
}

?>
