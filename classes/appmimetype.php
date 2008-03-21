<?php
/**
 * Class definition of the App_Mimetype class
 * @package PAPPI
 */

/**
 * The App_Mimetype class
 *
 * All mimetype related things.
 * @package PAPPI
 */
class App_Mimetype {
	
	public $type;
	public $subtype;
	public $parameters;
	
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
		"xslt" => "application/xslt+xml",
		"bin" => "application/octet-stream");
	
	/**
	 * Create a new Media type object.
	 *
	 * @param string $input The full Content-type header as defined in 
	 * {@link http://tools.ietf.org/html/rfc2045#section-5RFC 2045} or an
	 * extension, without the dot.
	 */
	public function __construct($input) {
		if ( strpos($input, "/") === FALSE ) {
			if ( array_key_exists($input, $this->extensions) ) {
				$this->parse_mime($this->extensions[$input]);
			} else {
				$this->parse_mime("application/octet-stream");
			}
		} else {
			$this->parse_mime($input);
		}
	}
	
	private function parse_mime($mime) {
		// mime == application/atom+xml; type=entry; charset="utf-8"
	
		// remove whitespace, lowercase
		$mime = strtolower(str_replace(" ","",$mime));
		
		// split on first ;
		$arr = explode(";",$mime, 2);
		
		if ( is_array($arr) && count($arr) > 1) {
			$params = $arr[1];
		}
		$fulltype = $arr[0];

		
		// $fulltype == type/subtype
		$full_parts = explode("/",$fulltype);
		$this->type = $full_parts[0];
		$this->subtype = $full_parts[1];
		
		// params
		$this->parameters = array();
		if ( isset($params) ) {
			$params_parts = explode(";",str_replace("\"","",$params));
			
			foreach($params_parts as $param) {
				$param_split = explode("=",$param);
				$attribute = $param_split[0];
				$value = $param_split[1];
				
				$this->parameters[$attribute] = $value;
			}
		}
	}
	
	/**
	 * Compare two content-types.
	 *
	 * Compares type and subtype.
	 * @param App_Mimetype $other The mimetype to compare.
	 */
	public function is_a($other) {
		// type
		if ($this->type == "*" || $other->type == "*") {
		} elseif ( $this->type == $other->type) {
		} else {
			return FALSE;
		}
		
		// subtype
		if ($this->subtype == "*" || $other->subtype == "*") {
		} elseif ( $this->subtype == $other->subtype) {
		} else {
			return FALSE;
		}
		
		return TRUE;
	}
	
	public function get_extension() {
		$mimetest = $this->type."/".$this->subtype;
		
		$extensions = array_flip($this->extensions);
		
		if ($mimetest == "application/atom+xml") {
			return "atom";
		} else if ( isset($extensions[$mimetest] ) ) {
			return $extensions[$mimetest];
		} else {
			return "bin";
		}
	}
	
	public function parameter_exists($name) {
		return array_key_exists($name, $this->parameters);
	}
	
	public function to_string() {
		$str = $this->type."/".$this->subtype;
		foreach( array_keys($this->parameters) as $param ) {
			$str = $str . "; ".$param."=".$this->parameters[$param];
		}
		return $str;
	}
	
	public function __toString() {
		return $this->to_string();
	}

}
