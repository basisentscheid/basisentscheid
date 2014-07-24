<?
/**
 * inc/classes/Login.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class Login {

	// false or object of logged in member
	public static $member = false;
	// false or object of logged in admin
	public static $admin  = false;


	/**
	 *
	 */
	public static function init() {

		session_save_path("var/sessions");
		session_name("BASISENTSCHEIDSESSION");
		//ini_set("session.gc_maxlifetime", 86400);
		ini_set("session.use_cookies", 1);
		ini_set("session.use_only_cookies", 1);
		session_start();

		// get logged in member or admin
		if (!empty($_SESSION['member'])) {
			self::$member = new Member($_SESSION['member']);
			// automatically logout if member was deleted from the database
			if (!self::$member->id) self::logout();
		} elseif (!empty($_SESSION['admin'])) {
			self::$admin = new Admin($_SESSION['admin']);
			// automatically logout if admin was deleted from the database
			if (!self::$admin->id) self::logout();
		}

	}


	/**
	 *
	 */
	public static function logout() {
		self::$member = false;
		self::$admin  = false;
		unset($_SESSION['member'], $_SESSION['admin']);
	}


	/**
	 *
	 * @param unknown $allowed_users
	 */
	public static function access($allowed_users) {
		switch ($allowed_users) {
		case "member":
			if (Login::$member) return;
			break;
		case "admin":
			if (Login::$admin) return;
			break;
		case "user":
			if (Login::$member or Login::$admin) return;
			break;
		default:
			trigger_error("Unknown allowed users keyword", E_USER_ERROR);
		}
		error("Access denied");
	}


}
