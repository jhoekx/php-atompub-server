<?php
/**
 * Class definition of the App_Accept_Encoding class
 * @package PAPPI
 */

/**
 * The App_Accept_Encoding class
 *
 * All accept header related things.
 * @package PAPPI
 */
class App_Accept_Encoding {
	
	public $codings;
	
	/**
	 * Create a new Accept_Encoding object.
	 *
	 * @param string $header The full Accept-Encoding header.
	 */
	public function __construct($header) {
		$encodings = explode(",", str_replace(" ","",strtolower($header)) );
		
		$this->codings = array();
		$todo = array();
		
		foreach ( $encodings as $encoding ) {
			$split = explode(";",$encoding);
			
			if ( is_array($split) && count($split) > 1 ) {
				$coding = $split[0];
				
				$qvalues = explode("=",$split[1]);
				$qvalue = $qvalues[1];
				
				$this->codings[$coding] = (float)$qvalue;
			} else {
				$coding = $split[0];
				$this->codings[$coding] = 1;
			}
		}
	}
	
	/**
	 * Check if the encoding is accepted
	 *
	 * @param array $arr The server defined values, e.g. array("gzip"=>1,"identity"=>0.5)
	 */
	public function preferred( $arr ) {
		$keys = array_keys($arr);
		$matches = array();
		
		foreach ( $keys as $key ) {
			if ( array_key_exists($key, $this->codings) ) {
				$matches[$key] = $arr[$key] * $this->codings[$key];
			}
		}
		
		if ( count($matches) > 0 ) {
			arsort($matches);
			$sorted_keys = array_keys($matches);
			
			if ($matches[ $sorted_keys[0] ] > 0.0001) {
				return $sorted_keys[0];
			}
		}
		
		return "identity";
	}
}

?>