<?php

require_once("appuri.php");
require_once("httpexception.php");
require_once("simple_bb_code.php");

class phpbb_Forum {

	private $db;
	private $base_uri;

	public function __construct($base_uri) {
		$this->db = new PDO("mysql:host=localhost;dbname=phpbb","phpbb","");
		$this->base_uri = new URI($base_uri."phpbb/");
	}
	
	public function get_listing($forum_id) {
		$sql = "SELECT t.topic_first_post_id AS 'URI',
		               t.topic_time AS 'Edit'
		        FROM phpbb_topics AS t 
		        WHERE (t.forum_id=:id)
				ORDER BY t.topic_time DESC";
		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $forum_id);
		$stmt->execute();
		$ress = $stmt->fetchAll();
		
		for ($i=0; $i<count($ress); $i++) {
			$ress[$i]["URI"] = $this->base_uri.$ress[$i]["URI"].".atomentry";
		}
		
		return json_encode($ress);
	}
	
	public function listing_last_modified($forum_id) {
		$sql = "SELECT t.topic_time AS 'Edit'
		        FROM phpbb_topics AS t 
		        WHERE (t.forum_id=:id)
				ORDER BY t.topic_time DESC
		        LIMIT 1";
		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $forum_id);
		$stmt->execute();
		$res = $stmt->fetch();
		
		return $res["Edit"];
	}

	public function get_post($id) {
		$sql = "SELECT p.post_time AS 'Published',
		               p.post_edit_time AS 'Edited',
				       p.post_subject AS 'Title',
				       p.post_text AS 'Content',
		               p.post_id AS 'Id',
		               u.username AS 'Author',
		               p.bbcode_bitfield AS 'bbcode_bitfield',
		               p.bbcode_uid AS 'bbcode_uid'
		        FROM phpbb_posts AS p,
		             phpbb_users AS u
		        WHERE (p.post_id=:id AND p.poster_id=u.user_id)";

		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $id);
		$stmt->execute();
		$res = $stmt->fetch();
		
		$doc = DOMImplementation::createDocument("http://www.w3.org/2005/Atom","entry");

		if ($res["bbcode_bitfield"]) {
			$res["Content"] = str_replace(array('&#58;', '&#46;'), array(':', '.'), $res["Content"]);
			$res["Content"] = str_replace(":".$res["bbcode_uid"], "", $res["Content"]);
			
			$bb = new Simple_BB_Code();
			$res["Content"] = $bb->parse($res["Content"]);
		}
		$this->add_result($res, $doc->documentElement);
		
		return $doc;
	}
	public function post_last_modified($id) {
		$sql = "SELECT p.post_time AS 'Published',
		               p.post_edit_time AS 'Edited'
		        FROM phpbb_posts AS p
		        WHERE (p.post_id=:id)";

		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $id);
		$stmt->execute();
		$res = $stmt->fetch();
		
		if ($res["Edited"]==0) {
			return $res["Published"];
		} else {
			return $res["Edited"];
		}
	}
	public function next_post_id() {
		$sql = "SELECT post_id AS 'Id'
		        FROM phpbb_posts 
		        ORDER BY post_id DESC
		        LIMIT 1";

		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->execute();
		$res = $stmt->fetch();
		
		return (int)$res["Id"] + 1;
	}
	public function next_topic_id() {
		$sql = "SELECT topic_id AS 'Id'
		        FROM phpbb_topics 
		        ORDER BY topic_id DESC
		        ";

		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->execute();
		$res = $stmt->fetch();

		return (int)$res["Id"] + 1;
	}
	
	public function post_exists($post_id) {
		$sql = "SELECT poster_id FROM phpbb_posts WHERE post_id=:id";
		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id",$post_id);
		$stmt->execute();
		$res = $stmt->fetchAll();
		if (count($res)>0) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function save_post($forum_id, $post_id, $data) {
		// read info
		$doc = DOMDocument::loadXML($data);
		
		$author = "2";
		$authorname = "Jeroen";
		$title = $doc->getElementsByTagName("title")->item(0)->textContent;
		$text = $doc->getElementsByTagName("content")->item(0)->textContent;
		$time = time();
		
		if ( $this->post_exists($post_id) ) {
			// update post
			$sql = "UPDATE phpbb_posts SET post_subject=:title, post_text=:text WHERE post_id=:id";
			$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
			$stmt->bindParam(":title", $title);
			$stmt->bindParam(":text", $text);
			$stmt->bindParam(":id", $post_id);
			$stmt->execute() or die("ERR");
			
			// update topic
			$sql = "UPDATE phpbb_topics SET topic_title:title WHERE first_post_id=:id";
			$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
			$stmt->bindParam(":title", $title);
			$stmt->bindParam(":id", $post_id);
			$stmt->execute() or die("ERR");
		} else {
			// create topic
			$sql = "INSERT INTO phpbb_topics (forum_id, topic_title, topic_poster, topic_time, topic_first_poster_name, topic_last_poster_name, topic_last_poster_id, topic_last_post_time) VALUES (:forumid, :title, :author, :time, :name, :name, :author, :time)";
			$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
			$stmt->bindParam(":forumid", $forum_id);
			$stmt->bindParam(":title", $title);
			$stmt->bindParam(":author", $author);
			$stmt->bindParam(":time", $time);
			$stmt->bindParam(":name", $authorname);
			$stmt->execute() or die("ERR");
			
			// last topic id
			$topic_id = $this->next_topic_id() - 1;
	
			// create post
			$sql = "INSERT INTO phpbb_posts (topic_id, forum_id, poster_id, post_time, post_subject, post_text) VALUES (:topicid, :forumid, :posterid, :posttime, :postsubject, :posttext)";
			$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
			$stmt->bindParam(":forumid", $forum_id);
			$stmt->bindParam(":topicid", $topic_id);
			$stmt->bindParam(":postsubject", $title);
			$stmt->bindParam(":posterid", $author);
			$stmt->bindParam(":posttime", $time);
			$stmt->bindParam(":posttext", $text);
			$stmt->execute() or die("ERR");
			
			// last post id
			$post_id = $this->next_post_id() - 1;
			
			// update topic information
			$sql = "UPDATE phpbb_topics SET topic_first_post_id=:postid, topic_last_post_id=:postid WHERE topic_id=:topicid";
			$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
			$stmt->bindParam(":postid", $post_id);
			$stmt->bindParam(":topicid", $topic_id);
			$stmt->execute() or die("ERR 2");
		}
	}
	
	public function remove_post($post_id) {
		$sql = "DELETE FROM phpbb_posts WHERE post_id=:id";
		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $post_id);
		$stmt->execute() or die("ERR");
		
		$sql = "DELETE FROM phpbb_topics WHERE topic_first_post_id=:id";
		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $post_id);
		$stmt->execute() or die("ERR");
	}
	
	public function get_feed($id, $start, $end) {
		$sql = "SELECT p.post_time AS 'Published',
		               p.post_edit_time AS 'Edited',
				       p.post_subject AS 'Title',
				       p.post_text AS 'Content',
		               p.post_id AS 'Id',
		               u.username AS 'Author'
		        FROM phpbb_topics AS t,
		             phpbb_posts AS p,
		             phpbb_users AS u
		        WHERE (
		            t.forum_id=:id 
		            AND t.topic_first_post_id = p.post_id 
		            AND p.poster_id = u.user_id
		        )
				LIMIT $start,$end";

		$stmt = $this->db->prepare($sql) or die("Error creating STMT!");
		$stmt->bindParam(":id", $id);
		$stmt->execute();
		$res = $stmt->fetchAll();
		
		$doc = DOMImplementation::createDocument("http://www.w3.org/2005/Atom","feed");
		
		for ($i=0; $i<count($res); $i++) {
			$entry = $doc->createElementNS("http://www.w3.org/2005/Atom","entry");
			$this->add_result($res[$i], $entry);
			$doc->documentElement->appendChild($entry);
		}
		
		return $doc;
	}
	
	private function add_result($res, $element) {
		$doc = $element->ownerDocument;
	
		$title = $doc->createElementNS("http://www.w3.org/2005/Atom","title");
		$title->appendChild( $doc->createTextNode($res["Title"]) );
		$element->appendChild($title);
		
		$author = $doc->createElementNS("http://www.w3.org/2005/Atom","author");
		$name = $doc->createElementNS("http://www.w3.org/2005/Atom","name");
		$name->appendChild( $doc->createTextNode($res["Author"]) );
		$author->appendChild($name);
		$element->appendChild($author);
		
		$published = $doc->createElementNS("http://www.w3.org/2005/Atom","published");
		$published->appendChild( $doc->createTextNode(date(DATE_ATOM, $res["Published"])) );
		$element->appendChild($published);

		if ($res["Edited"]==0) {
			$edit = $res["Published"];
		} else {
			$edit = $res["Edited"];
		}
		$updated = $doc->createElementNS("http://www.w3.org/2005/Atom","updated");
		$updated->appendChild( $doc->createTextNode(date(DATE_ATOM, $edit)) );
		$element->appendChild($updated);
		$edited = $doc->createElementNS("http://www.w3.org/2007/app","app:edited");
		$edited->appendChild( $doc->createTextNode(date(DATE_ATOM, $edit)) );
		$element->appendChild($edited);
		
		$content = $doc->createElementNS("http://www.w3.org/2005/Atom","content");
		$content->appendChild( $doc->createTextNode($res["Content"]) );
		$content->setAttribute("type","html");
		$element->appendChild($content);
		
		$domain = explode("/", str_replace("http://","",$this->base_uri));
		if ( is_array($domain) ) {
			$domain = $domain[0];
		}
		$year = date("Y",$edit); $month = date("m",$edit); $day = date("d",$edit);
		$atom_id = "tag:".$domain.",".$year."-".$month."-".$day.":phpbb/".$res["Id"];
		
		$id_el = $doc->createElementNS("http://www.w3.org/2005/Atom","id");
		$id_el->appendChild( $doc->createTextNode($atom_id) );
		$element->appendChild($id_el);
		
		$edit = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
		$edit->setAttribute("rel","edit");
		$edit->setAttribute("href",$this->base_uri.$res["Id"].".atomentry");
		$element->appendChild($edit);
	}
}
