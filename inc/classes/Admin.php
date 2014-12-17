<?
/**
 * Admin
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Admin extends Relation {

	// database table
	public $username;
	public $password;

	protected $create_fields = array("username", "password");


	/**
	 * password form
	 */
	public function dbtableadmin_edit_password() {
?>
<input type="password" name="password">
<input type="password" name="password2">
<?
	}


	/**
	 * encrypt password
	 *
	 * @return boolean
	 */
	function dbtableadmin_beforesave_password() {

		if ($this->id) {
			// existing admin
			if (empty($_POST['password']) and empty($_POST['password2'])) return null;
		} else {
			// new admin
			if (empty($_POST['password']) or empty($_POST['password2'])) {
				warning("The password fields must not be empty!");
				return false;
			}
		}

		if ($_POST['password'] != $_POST['password2']) {
			warning("The two password fields do not match!");
			return false;
		}

		$this->password = crypt($_POST['password']);

		return true;
	}


}
