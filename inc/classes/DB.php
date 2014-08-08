<?
/**
 * inc/classes/DB.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class DB {

	// values returned by fetch functions for boolean columns
	const value_true  = "t";
	const value_false = "f";

	/**
	 * nummer of levels into transaction - to avoid nested transactions
	 *
	 * @var integer
	 * @access private
	 */
	private static $transaction_depth = 0;


	/**
	 * class constructor
	 */
	public static function __static() {
		$dbconn = pg_connect(DATABASE_CONNECT);
	}


	/**
	 * escape strings for SQL statements
	 *
	 * @param string  $str
	 * @return string
	 */
	public function m($str) {
		return "'".pg_escape_string($str)."'";
	}


	/**
	 * escape strings for SQL statements, add singlequotes and handle boolean and NULL values
	 *
	 * @param mixed   $value
	 * @return string
	 */
	public function m_null($value) {
		if (is_null($value)) return "NULL";
		if ($value===true)  return "TRUE";
		if ($value===false) return "FALSE";
		return "'".pg_escape_string($value)."'";
	}


	/**
	 * convert boolean values from Postgres
	 *
	 * @param mixed   $value (reference)
	 */
	public function pg2bool(&$value) {
		if ($value===self::value_false) {
			$value = false;
		} elseif ($value===self::value_true) {
			$value = true;
		}
	}


	/**
	 * convert values for boolean Postgres columns
	 *
	 * @param string  $value
	 * @return mixed
	 */
	public function bool2pg($value) {
		if ($value) return "TRUE";
		if (is_null($value)) return "NULL";
		return "FALSE";
	}


	/**
	 * Like pg_query(), but with error management
	 *
	 * @param string  $sql
	 * @param unknown $showsuccess (optional)
	 * @return unknown
	 */
	public function query($sql, $showsuccess=false) {

		$result = pg_query($sql);

		if ($result) {
			if ($showsuccess) {
				if ($showsuccess===true) {
					?><p>Die Eingaben wurden erfolgreich in die Datenbank geschrieben.</p><?
				} else {
					?><p><?=$showsuccess?></p><?
				}
			}
			// DEBUG
			// echo "<p>SQL statement (successful):<br>".nl2br(h($sql))."</p>";
			// echo "SQL statement (successful):\n".$sql."\n";
			// self::sql_error($sql, "SQL statement (successful): <i>".pg_last_error()."</i>");
		} else {
			self::sql_error($sql, "Postgres Error <i>".pg_last_error()."</i>");
		}

		return $result;
	}


	/**
	 * Display an error with SQL statement and debug information
	 *
	 * @param string  $sql
	 * @param string  $errormsg
	 */
	private function sql_error($sql, $errormsg) {

		$debug_backtrace = debug_backtrace();
		// determine source file of error
		foreach ( $debug_backtrace as $tracepart ) {
			if ( basename($tracepart['file']) != "DB.php" ) break;
		}

		if (php_sapi_name()!="cli") {
			// Fehlermeldung in HTML
			$errormsg .= " in SQL Query <i>".$sql."</i> called from <i>".$tracepart['file']."</i> on line <i>".$tracepart['line']."</i>";
		} else {
			// Fehlermeldung als Text
			$errormsg .= " in SQL Query ".$sql." called from ".$tracepart['file']." on line ".$tracepart['line'];
		}
		trigger_error($errormsg, E_USER_WARNING);

	}


	/**
	 *
	 * @param unknown $result
	 * @return unknown
	 */
	function num_rows($result) {
		return pg_num_rows($result);
	}


	/**
	 *
	 * @param unknown $result
	 * @return unknown
	 */
	function fetch_row($result) {
		return pg_fetch_row($result);
	}


	/**
	 *
	 * @param unknown $result
	 * @return unknown
	 */
	function fetch_assoc($result) {
		return pg_fetch_assoc($result);
	}


	/**
	 *
	 * @param unknown $result
	 * @return unknown
	 */
	function fetch_array($result) {
		return pg_fetch_array($result);
	}


	/**
	 * call the constructor with $from_fetch_object
	 *
	 * @param resource $result
	 * @param string  $classname
	 * @return object
	 */
	function fetch_object($result, $classname) {
		return pg_fetch_object($result, null, $classname, array(0, true));
	}


	/**
	 * query and fetch one row
	 *
	 * @param string  $sql
	 * @return mixed
	 */
	public function fetchassoc($sql) {
		$result = self::query($sql);
		if ( $result and $row = pg_fetch_assoc($result) ) {
			pg_free_result($result);
			return $row;
		} else {
			return false;
		}
	}


	/**
	 * query and fetch one field
	 *
	 * @param string  $sql
	 * @return mixed
	 */
	public function fetchfield($sql) {
		$result = self::query($sql);
		$row = pg_fetch_row($result);
		pg_free_result($result);
		return $row[0];
	}


	/**
	 * query and fetch all rows at once
	 *
	 * @param string  $sql
	 * @return array
	 */
	public function fetchfieldarray($sql) {
		$result = self::query($sql);
		$fieldarray = array();
		while ( $row = pg_fetch_row($result) ) $fieldarray[] = $row[0];
		pg_free_result($result);
		return $fieldarray;
	}


	/**
	 * query and get number of found rows
	 *
	 * @param string  $sql
	 * @return mixed
	 */
	public function numrows($sql) {
		$result = self::query($sql);
		$rows = pg_num_rows($result);
		pg_free_result($result);
		return $rows;
	}


	/**
	 * INSERT one record
	 *
	 * @param string  $table
	 * @param array   $fields_values (optional) Associative array with database fields as keys and unescaped values as values (optional)
	 * @param unknown $insert_id     (optional, reference)
	 * @return unknown
	 */
	public function insert($table, $fields_values=array(), &$insert_id=false) {

		//self::transaction_start();

		foreach ($fields_values as &$value) {
			$value = DB::m_null($value);
		}

		$sql = "INSERT INTO ".$table." (".join(",", array_keys($fields_values)).") VALUES (".join(",", $fields_values).")";

		if ($insert_id!==false) {
			$sql .= " RETURNING id";
		}

		if ( ! $result = self::query($sql) ) {
			//self::transaction_rollback();
			return $result;
		}

		if ($insert_id!==false) {
			$row = pg_fetch_row($result);
			$insert_id = $row[0];
		}

		//self::transaction_commit();

		return $result;
	}


	/**
	 * UPDATE one or more records
	 *
	 * @param string  $table
	 * @param string  $where         WHERE part of the SQL statement
	 * @param array   $fields_values (optional) Associative array with database fields as keys and unescaped values as values (optional)
	 * @param string  $extra         (optional)
	 * @return unknown
	 */
	public function update($table, $where, $fields_values=array(), $extra=false) {

		$fields_values = self::convert_fields_values($fields_values);

		if ($extra) {
			$fields_values[] = $extra;
		}

		$sql = "UPDATE ".$table." SET ".join(', ', $fields_values);
		$sql .= self::where_and( $where );

		return self::query($sql);

	}


	/**
	 * DELETE one or more records
	 *
	 * @param string  $table
	 * @param string  $where (optional) WHERE part of the SQL statement
	 * @return unknown
	 */
	public function delete($table, $where=false) {

		$sql = "DELETE FROM ".$table.self::where_and($where);

		return self::query($sql);
	}


	/**
	 * simulate INSERT ON DUPLICATE KEY UPDATE
	 *
	 * @param string  $table
	 * @param array   $fields_values
	 * @param array   $keys
	 * @param integer $insert_id     (optional, reference)
	 * @return unknown
	 */
	function insert_or_update($table, array $fields_values, array $keys, &$insert_id=false) {

		$fields_values_update = array();
		$where = array();
		foreach ($fields_values as $key => $value) {
			if (in_array($key, $keys)) {
				$where[$key] = $value;
			} else {
				$fields_values_update[$key] = $value;
			}
		}
		$where = self::convert_fields_values($where);

		self::transaction_start();

		$result = self::update($table, $where, $fields_values_update);
		if ( !pg_affected_rows($result) ) {
			$result = self::insert($table, $fields_values, $insert_id);
		}

		self::transaction_commit();

		return $result;
	}


	/**
	 * convert an associated array of field value pairs to an indexed array of SQL assignments
	 *
	 * @param array   $fields_values array('col1'=>"value", 'col2'=>true)
	 * @return array               array("column1='value'", "column2=TRUE")
	 */
	public function convert_fields_values($fields_values) {
		$converted = array();
		foreach ( $fields_values as $key => $value ) {
			$converted[] = $key."=".self::m_null($value);
		}
		return $converted;
	}


	/**
	 * combines conditions with WHERE and AND
	 *
	 * Conditions have to be strings "uid=1", not assoc arrays ("uid"=>1)!
	 *
	 * @param string  multiple strings or arrays of stings as arguments
	 * @return string
	 */
	public function where_and() {
		$args = func_get_args();

		$conditions = self::array_flat($args);

		if ( !count($conditions) ) return "";

		// Ignore repeated conditions
		$conditions = array_unique($conditions);

		return " WHERE ".join($conditions, " AND ");
	}


	/**
	 * converts a multidimensional indexed array to singledimensional array
	 *
	 * @param array   $array
	 * @return array
	 */
	private function array_flat($array) {
		$return = array();
		foreach ( $array as $element ) {
			if ( is_array($element) ) {
				$return = array_merge($return, self::array_flat($element));
			} elseif ( trim($element) ) {
				$return[] = $element;
			}
		}
		return $return;
	}


	/**
	 * START TRANSACTION
	 */
	public function transaction_start() {
		if ( self::$transaction_depth++ == 0 ) DB::query("START TRANSACTION");
	}


	/**
	 * COMMIT
	 */
	public function transaction_commit() {
		if ( --self::$transaction_depth == 0 ) DB::query("COMMIT");
	}


	/**
	 * ROLLBACK
	 */
	public function transaction_rollback() {
		if ( --self::$transaction_depth == 0 ) DB::query("ROLLBACK");
	}


}


DB::__static();
