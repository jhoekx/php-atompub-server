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
	
	/**
	 * Create a new Media type object.
	 *
	 * @param string $mimetype The full Content-type header as defined in 
	 * {@link http://tools.ietf.org/html/rfc2045#section-5RFC 2045}.
	 */
	public function __construct($mimetype) {
		$this->parse_mime($mimetype);
	}
	
	private function parse_mime($mime) {
		// mime == application/atom+xml; type=entry; charset="utf-8"
	
		// remove whitespace, lowercase
		$mime = strtolower(str_replace(" ","",$mime));
		
		// split on ;
		$arr = explode(";",$mime, 2);
		
		if ( is_array($arr) && count($arr) > 1) {
			$fulltype = $arr[0];
			$params = $arr[1];
		} else {
			$fulltype = $arr[0];
		}
		
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

}

?>