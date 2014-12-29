<?
/**
 * Ballot
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Ballot extends Relation {

	// database table
	public $period;
	public $name;
	public $ngroup;
	public $opening;
	public $agents;
	public $approved;

	protected $boolean_fields = array("approved");
	protected $update_fields = array("name", "agents", "opening", "ngroup");

	private $period_obj;
	private $ngroup_obj;


	/**
	 * create a new ballot
	 *
	 * @param array   $fields (optional)
	 * @return void
	 */
	public function create( array $fields = array("name", "agents", "period", "opening", "ngroup") ) {

		$fields_values = array();
		foreach ( $fields as $field ) {
			$fields_values[$field] = $this->$field;
		}
		DB::insert("ballot", $fields_values, $this->id);

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
	 * get the referenced ngroup
	 *
	 * @return object
	 */
	function ngroup() {
		if (!is_object($this->ngroup_obj)) $this->ngroup_obj = new Ngroup($this->ngroup);
		return $this->ngroup_obj;
	}


	/**
	 * assign a member to this ballot
	 *
	 * @param Member  $member
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
		DB::insert_or_update("offlinevoter", $fields_values, $keys);
	}


	/**
	 * description of a ballot for use notification mails
	 *
	 * @return string
	 */
	public function description_for_mail() {
		return
		_("Name or location:  ").$this->name."\n".
			_("Group at location: ").$this->ngroup()->name."\n".
			sprintf(_("Opening hours:     %s to %s"), timeformat($this->opening), BALLOT_CLOSE_TIME)."\n".
			_("Agents:            ").$this->agents."\n";
	}


}
