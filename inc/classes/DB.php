<?
/**
 * inc/classes/DB.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class DB {

	/**
	 * Nummer of levels into transaction. To avoid nested transactions.
	 *
	 * @var integer
	 * @access private
	 */
	private static $transaction_depth = 0;


	/**
	 * Class constructor
	 */
	public static function __static() {
		$dbconn = pg_connect(DATABASE_CONNECT);
	}


	/**
	 * Escape strings for SQL-statements and add singlequotes
	 *
	 * @param string  $str Input string
	 * @return string      Output string; Quotes (" / ') and \ will be backslashed
	 */
	public function m($str) {
		return "'".pg_escape_string($str)."'";
	}


	/**
	 * Escape strings for SQL-statements, add singlequotes and handle NULL values
	 *
	 * @param mixed   $value Input
	 * @return string      Output string; Quotes (" / ') and \ will be backslashed
	 */
	public function m_null($value) {
		if (is_null($value)) return "NULL";
		return "'".pg_escape_string($value)."'";
	}


	/**
	 * convert boolean values from Postgres
	 *
	 * @param mixed   $value (reference)
	 */
	public function pg2bool(&$value) {
		if ($value=="f") {
			$value = false;
		} elseif ($value=="t") {
			$value = true;
		}
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
					?><p><? echo $showsuccess?></p><?
				}
			}
			if (false) { // Debug
				?><p>SQL-Statement (erfolgreich):<br><? echo nl2br(htmlentities($sql))?></p><?
			}
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
	 * Direkte Abfrage eines Datensatzes
	 *
	 * @param string  $sql SQL-Statement
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
	 * Ein Feld aus der Datenbank auslesen (vor allem für COUNT(*))
	 *
	 * @param string  $sql SQL-Statement
	 * @return mixed
	 */
	public function fetchfield($sql) {
		$result = self::query($sql);
		$row = pg_fetch_row($result);
		pg_free_result($result);
		return $row[0];
	}


	/**
	 * Alle Werte eines Feldes as Array zurückgeben (vor allem für SHOW TABLES u.ä.)
	 *
	 * @param string  $sql SQL-Statement
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
	 * Ein Feld aus der Datenbank auslesen (vor allem für COUNT(*))
	 *
	 * @param string  $sql SQL-Statement
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

		$id = 0;

		foreach ($fields_values as $key => $value) {
			$fields_values[$key] = DB::m_null($fields_values[$key]);
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
	 * @param unknown $extra         (optional)
	 * @return unknown
	 */
	public function update($table, $where, $fields_values=array(), $extra=false) {

		foreach ($fields_values as $key => $value) {
			$fields_values[$key] = $key."=".DB::m_null($value);
		}

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
	 * Combines conditions with WHERE and AND
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
	 * Converts a multidimensional indexed array to singledimensional array
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
