<?php
/**
 * Class definition of the App_Uri_Handler class
 * @package PAPPI
 */

/**
 * The App_Uri_Handler class
 *
 * Provides URI related services.
 * @package PAPPI
 */
class App_Uri_Handler {

	/**
	 * Return the components of an URI as defined in 
	 * {@link http://tools.ietf.org/html/rfc3986#section-3} RFC 3986}.
	 * 
	 * @param string $uri The URI.
	 * @return mixed An associative array, with the components in lowercase.
	 */
	public function get_uri_components($uri) {
	
		$ocomps = parse_url($uri);
		
		if ( $ocomps != FALSE ) {
			if ( array_key_exists("scheme",$ocomps) ) {
				$comps["scheme"] = $ocomps["scheme"];
			}
			if ( array_key_exists("host",$ocomps) ) {
				$comps["authority"] = $ocomps["host"];
			}
			if ( array_key_exists("path",$ocomps) ) {
				$comps["path"] = $ocomps["path"];
			}
			if ( array_key_exists("query",$ocomps) ) {
				$comps["query"] = $ocomps["query"];
			}
			if ( array_key_exists("fragment",$ocomps) ) {
				$comps["fragment"] = $ocomps["fragment"];
			}
		}
		
		return $comps;
	}
	
	/**
	 * Resolve a relative URI according to a specied base uri
	 *
	 * @param string $uri The URI.
	 * @param string $base_uri The base URI, can also be relative.
	 * @return string The resolved URI.
	 */
	public function resolve_relative_uri($uri, $base_uri) {
	
		$T = array();
		$R = $this->get_uri_components($uri);
		$Base = $this->get_uri_components($base_uri);
		
		//print_r($R);
		//print_r($Base);
		
		if ( array_key_exists("scheme",$R) ) {
			// absolute URI
			$T["scheme"] = $R["scheme"];
			
			if ( array_key_exists("authority",$R) ) {
				$T["authority"] = $R["authority"];
			}
			if ( array_key_exists("path",$R) ) {
				$T["path"] = $this->remove_dot_segments($R["path"]);
			}
			if ( array_key_exists("query",$R) ) {
				$T["query"] = $R["query"];
			}
		} else {
			if ( array_key_exists("authority",$R) ) {
				$T["authority"] = $R["authority"];

				if ( array_key_exists("path",$R) ) {
					$T["path"] = $this->remove_dot_segments($R["path"]);
				}
				if ( array_key_exists("query",$R) ) {
					$T["query"] = $R["query"];
				}
			} else {
				if ( !array_key_exists("path",$R) ) {
					if ( array_key_exists("path",$Base) ) {
						$T["path"] = $Base["path"];
					}
					
					if ( array_key_exists("query",$R) ) {
						$T["query"] = $R["query"];
					} else {
						if ( array_key_exists("query",$Base) ) {
							$T["query"] = $Base["query"];
						}
					}
				} else {
					if ( $R["path"][0] == "/") {
						$T["path"] = $this->remove_dot_segments($R["path"]);
					} else {
						$T["path"] = $this->merge($Base, $R);
						$T["path"] = $this->remove_dot_segments($T["path"]);
					}
					if ( array_key_exists("query",$R) ) {
						$T["query"] = $R["query"];
					}
				}
				if ( array_key_exists("authority",$Base) ) {
					$T["authority"] = $Base["authority"];
				}
			}
			if ( array_key_exists("scheme",$Base) ) {
				$T["scheme"] = $Base["scheme"];
			}
		}
		
		if ( array_key_exists("fragment",$R) ) {
			$T["fragment"] = $R["fragment"];
		}
		
		return $T;
	}
	
	/**
	 * Resolve an uri in a DOMDocument, taking xml:base into account
	 *
	 * @param DOMNode $node The node, whose textContent is an URI.
	 * @param string $external_uri An external uri to use as base uri of the entity.
	 * @return string The resolved URI.
	 */
	public function resolve_uri($node, $external_uri) {
		$NS = "http://www.w3.org/XML/1998/namespace";
		
		$uri = $node->textContent;
		
		if ( array_key_exists("scheme",$this->get_uri_components($uri)) ) {
			return $node->textContent;
		}
		
		if ( $node->nodeType == XML_ATTRIBUTE_NODE ) {
			$element = $node->ownerElement;
		} else {
			$element = $node;
		}

		$resolved_value = $this->walk($element, $uri);
		
		return $this->compose_uri( $this->resolve_relative_uri($resolved_value, $external_uri));

	}


	private function remove_dot_segments($path) {
		$input = $path;
		$output = array();
		
		while( isset($input[0]) ) {
			if ( strpos($input,"../") === 0 || strpos($input,"./") === 0 ) {
				if (strpos($input,"../")===0) {
					$input = substr($input,3);
				} else {
					$input = substr($input,2);
				}
			} else if ( strpos($input,"/../")===0 ||
						(
							strpos($input, "/..")===0 && 
								( 
									(isset($input[3]) && $input[3] == "/") ||
									!isset($input[3])
								)
						) 
					) {
				if ( strpos($input, "/../") === 0) {
					$input = "/".substr($input, 4);
				} else {
					$input = "/".substr($input, 3);
				}
				
				if ( $output[count($output)-1] ) {
					array_pop($output);
				}
				
			} else if ( strpos($input,"/./")===0 ||
						(
							strpos($input, "/.")===0 && 
								( 
									(isset($input[2]) && $input[2] == "/") ||
									!isset($input[2])
								)
						) 
					) {
				if ( strpos($input, "/./") === 0) {
					$input = "/".substr($input, 3);
				} else {
					$input = "/".substr($input, 2);
				}
			} else if ( $input === "." || $input === ".." ) {
				$input = "";
			} else {
				if ( strpos($input,"/") === 0 ) {
					$input = substr($input,1);
					
					if ( strpos($input,"/") === FALSE ) {
						array_push($output, "/".$input);
						$input = "";
					} else {
						array_push($output,"/".substr($input, 0, strpos($input, "/")));
						$input = substr($input, strpos($input,"/"));
					}
				} else {
					if ( strpos($input,"/") === FALSE ) {
						array_push($output, $input);
						$input = "";
					} else {
						array_push($output,substr($input, 0, strpos($input, "/")));
						$input = substr($input, strpos($input,"/"));
					}
				}
			}
		}
		
		return join($output, "");
	}
	
	private function merge($base, $rel) {
		if ( array_key_exists("authority",$base) && !array_key_exists("path",$base) ) {
			if ( array_key_exists("path",$rel) ) {
				return "/".$rel["path"];
			} else {
				return "/";
			}
		} else {
			
			if ( array_key_exists("path", $base) ) {
				$slashindex = strrpos($base["path"],"/");
				if ($slashindex === FALSE) {
					if ( array_key_exists("path",$rel) ) {
						return $rel["path"];
					}
				} else {
					return substr($base["path"],0,$slashindex+1).$rel["path"];
				}
			}
		}
	}
	
	private function compose_uri($parts) {
		$uri = array();
		
		if ( array_key_exists("scheme",$parts) ) {
			array_push($uri, $parts["scheme"]."://");
		}
		if ( array_key_exists("authority",$parts) ) {
			array_push($uri, $parts["authority"]);
		}
		if ( array_key_exists("path",$parts) ) {
			array_push($uri, $parts["path"]);
		}
		if ( array_key_exists("query",$parts) ) {
			array_push($uri, $parts["query"]);
		}
		if ( array_key_exists("fragment",$parts) ) {
			array_push($uri, $parts["fragment"]);
		}
		
		return join($uri, "");
	}
	
	private function walk ($element, $uri) {
		$NS = "http://www.w3.org/XML/1998/namespace";
		if( $element->nodeType != XML_ELEMENT_NODE ) {
			return $uri;
		}
		if ( $element->hasAttributeNS($NS,"base") ) {
			
			// resolve relative uri later on
			$new_uri = $element->getAttributeNS($NS,"base").$uri;
			
			$current_uri = $this->get_uri_components($new_uri);

			if ( array_key_exists("scheme",$current_uri) ) {
				return $new_uri;
			} else {
				if ( isset($element->parentNode) ) {
					return $this->walk($element->parentNode, $new_uri);
				} else {
					return $new_uri;
				}
			}
		} else {
			if ( isset($element->parentNode) ) {
				return $this->walk($element->parentNode, $uri);
			} else {
				return $uri;
			}
		}
	}
}
?>