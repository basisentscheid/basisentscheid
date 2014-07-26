<?
/**
 * inc/classes/URI.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class URI {

	// current relative URI without html entities
	public static $uri_plain;

	// current relative URI with html entities
	public static $uri;

	// query array without empty values
	private static $query = array();


	/**
	 * class constructor
	 */
	public static function __static() {

		self::$uri_plain = basename($_SERVER['REQUEST_URI']);
		self::$uri = h(self::$uri_plain);

		// strip empty parameters ("0" is not empty)
		self::$query = $_GET;
		self::strip_empty(self::$query);

	}


	/**
	 * recursively strip empty parameters
	 *
	 * - "" is empty.
	 * - Arrays without elements are empty.
	 * - "0" is not empty!
	 *
	 * @param array   $array (reference)
	 */
	private static function strip_empty(&$array) {
		foreach ($array as $key => $value) {
			if ($value==="") {
				unset($array[$key]);
			} elseif (is_array($value)) {
				self::strip_empty($array[$key]);
				if (!count($value)) unset($array[$key]);
			}
		}
	}


	/**
	 * build new URI
	 *
	 * Parameters with value null will be skipped.
	 *
	 * @param array   $params (optional)
	 * @param boolean $plain  (optional) return a URI without html entities
	 * @return string         URI
	 */
	public static function build(array $params=array(), $plain=false) {
		$uri = BN;
		if ($params and $query = http_build_query($params)) $uri .= "?".$query;
		if ($plain) return $uri;
		return h($uri);
	}


	/**
	 * add or replace parameters to the current URI
	 *
	 * Parameters with value null will be removed from the current URI.
	 *
	 * @param array   $params associative array
	 * @return string         URI with html entities
	 */
	public static function append(array $params) {
		$query_array = self::$query;
		foreach ( $params as $key => $value ) {
			$query_array[$key] = $value;
		}
		return self::build($query_array);
	}


	/**
	 * remove parameters from the current URI
	 *
	 * @param array   $keys  indexed array
	 * @param boolean $plain (optional) return a URI without html entities
	 * @return string        URI
	 */
	public static function strip(array $keys, $plain=false) {
		$query_array = self::$query;
		foreach ( $keys as $key ) {
			unset($query_array[$key]);
		}
		// if all elements are unset, the array behaves not as an array anymore
		if (!count($query_array)) $query_array = array();
		return self::build($query_array, $plain);
	}


	/**
	 * prepare a URI for appending additional parameters
	 *
	 * @param string  $uri URI with html entites
	 * @return string      URI with html entites
	 */
	public static function linkpart($uri) {
		if (strpos($uri, "?")===false) return $uri."?"; else return $uri."&amp;";
	}


	/**
	 * display hidden input fields for the current query
	 *
	 * Parameters with value null or "" will be removed.
	 *
	 * @param array   $params (optional) override parameters
	 */
	public static function hidden(array $params=array()) {
		$query = array_merge(self::$query, $params);
		self::hidden_recursive(false, $query);
	}


	/**
	 *
	 * @param unknown $arraykey
	 * @param unknown $query
	 */
	private static function hidden_recursive($arraykey, $query) {
		foreach ( $query as $key => $value ) {
			if ($value===null or $value==="") continue;
			if ($arraykey) {
				$name = $arraykey."[".$key."]";
			} else {
				$name = $key;
			}
			if (is_array($value)) {
				self::hidden_recursive($name, $value);
			} else {
				input_hidden($name, $value);
			}
		}
	}


}


URI::__static();
