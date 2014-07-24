<?
/**
 * inc/classes/URI.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class URI {


	private static $query;


	/**
	 * class constructor
	 */
	public static function __static() {
		parse_str($_SERVER['QUERY_STRING'], self::$query);
	}


	/**
	 * build URI
	 *
	 * @param array   $params
	 * @return string
	 */
	public static function build(array $params) {
		$uri = BN;
		if ($query = http_build_query($params)) $uri .= "?".$query;
		return $uri;
	}


	/**
	 * add or replace parameters to the current URI
	 *
	 * @param array   $params
	 * @return string
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
	 * @param array   $keys
	 * @return string
	 */
	public static function strip(array $keys) {
		$query_array = self::$query;
		foreach ( $keys as $key ) {
			unset($query_array[$key]);
		}
		// if all elements are unset, the array behaves not as an array anymore
		if (!count($query_array)) $query_array = array();
		return self::build($query_array);
	}


}


DB::__static();
