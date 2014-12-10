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
		// after logout on a non-public page
		if ( isset($_SESSION['redirects'][0]['POST']['action']) and $_SESSION['redirects'][0]['POST']['action'] == "logout" ) {
			redirect("index.php");
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


	/**
	 * check if a username meets the requirements
	 *
	 * @param string  $username
	 * @return boolean
	 */
	public static function check_username($username) {
		if (!$username) {
			warning(_("Please enter a user name!"));
			return false;
		}
		$len = mb_strlen($username);
		if ($len < 3) {
			warning(_("The user name must have at least 3 characters!"));
			return false;
		}
		if ($len > 32) {
			warning(_("The user name must have not more than 32 characters!"));
			return false;
		}
		if (lefteq($username, "#")) {
			warning(_("The user name must not begin with the character '#'!"));
			return false;
		}
		$sql = "SELECT COUNT(1) FROM members WHERE username=".DB::esc($username);
		if ( DB::fetchfield($sql) ) {
			warning(_("This user name is already used by someone else. Please try a different one!"));
			return false;
		}
		return true;
	}


	/**
	 * check if an entered password meets the requirements
	 *
	 * @param string  $password  (reference)
	 * @param string  $password2
	 * @return boolean
	 */
	public static function check_password(&$password, $password2) {
		if (!$password or !$password2) {
			warning(_("Please enter a password!"));
			$password = "";
			return false;
		}
		if ($password != $password2) {
			warning(_("The two password fields do not match!"));
			$password = "";
			return false;
		}
		if (mb_strlen($password) < 8) {
			warning(_("The password name must have at least 8 characters!"));
			$password = "";
			return false;
		}
		return true;
	}


	/**
	 * check if an email address is valid
	 *
	 * @param string  $mail
	 * @return boolean
	 */
	public static function check_mail($mail) {
		if (!$mail) {
			warning(_("Please enter an email address!"));
			return false;
		}
		if ( ! filter_var($mail, FILTER_VALIDATE_EMAIL) ) {
			warning(_("This email address is not valid!"));
			return false;
		}
		return true;
	}


}
