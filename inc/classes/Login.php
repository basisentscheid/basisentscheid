<?
/**
 * handle session and user login
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


abstract class Login {

	/**
	 * false or object of logged in member
	 *
	 * @var Member $member
	 */
	public static $member = false;

	/**
	 * false or object of logged in admin
	 *
	 * @var Admin $admin
	 */
	public static $admin  = false;


	/**
	 * to be called on every page
	 */
	public static function init() {

		session_save_path(DOCROOT."var/sessions");
		session_name("BASISENTSCHEIDSESSION");
		//ini_set("session.gc_maxlifetime", 86400);
		ini_set("session.use_cookies", "on");
		ini_set("session.use_only_cookies", "on");
		session_start();

		if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = self::generate_token(32);

		// get logged in member or admin
		if (!empty($_SESSION['member'])) {
			self::$member = new Member($_SESSION['member']);
			// automatically logout if member was deleted from the database
			if (!self::$member->id) self::logout();
			// prevent double logins
			unset($_SESSION['admin']);
		} elseif (!empty($_SESSION['admin'])) {
			self::$admin = new Admin($_SESSION['admin']);
			// automatically logout if admin was deleted from the database
			if (!self::$admin->id) self::logout();
			// prevent double logins
			unset($_SESSION['member']);
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
	 * make sure that only allowed users access a page
	 *
	 * @param string|array $allowed_users
	 * @param integer $ngroup        (optional) required if only entitled members are allowed
	 * @param boolean $redirect      (optional)
	 */
	public static function access($allowed_users, $ngroup=0, $redirect=false) {
		if (!is_array($allowed_users)) $allowed_users = array($allowed_users);
		foreach ( $allowed_users as $keyword) {
			switch ($keyword) {
			case "entitled":
				if (Login::$member and Login::$member->entitled($ngroup)) return;
				break;
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
		}
		if ($redirect) {
			warning(_("Access denied"));
			redirect();
		}
		error(_("Access denied"));
	}


	/**
	 * make sure that only allowed users perform an action
	 *
	 * @param string  $allowed_users
	 * @param integer $ngroup        (optional)
	 */
	public static function access_action($allowed_users, $ngroup=0) {
		self::access($allowed_users, $ngroup, true);
	}


	/**
	 * generate random token
	 *
	 * @param integer $length
	 * @param string  $chars  (optional)
	 * @return string
	 */
	public static function generate_token($length, $chars="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789") {
		$max = strlen($chars) - 1;
		$token = "";
		while ( $length-- > 0 ) $token .= $chars{ mt_rand(0, $max) };
		return $token;
	}


}
