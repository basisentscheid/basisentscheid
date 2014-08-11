<?
/**
 * Ballot
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ballot extends Relation {

	public $name;
	public $agents;
	public $period;
	public $approved;
	public $opening;

	protected $boolean_fields = array("approved");
	protected $update_fields = array("name", "agents", "opening");

	private $period_obj;


	/**
	 * create a new ballot
	 *
	 * @param array   $fields (optional)
	 */
	public function create( array $fields = array("name", "agents", "period", "opening") ) {

		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert("ballots", $fields_values, $this->id);

		$this->period()->select_ballot($this);

	}


	/**
	 * get the referenced period (read it only once from the database)
	 *
	 * @return object
	 */
	function period() {
		if (!is_object($this->period_obj)) $this->period_obj = new Period($this->period);
		return $this->period_obj;
	}


	/**
	 * assign a member to this ballot
	 *
	 * @param object  $member
	 * @param boolean $agent
	 */
	public function assign_member(Member $member, $agent=false) {
		$fields_values = array(
			'member' => $member->id,
			'period' => $this->period,
			'ballot' => $this->id,
			'agent'  => $agent
		);
		$keys = array("member", "period");
		DB::insert_or_update("voters", $fields_values, $keys);
	}


}
