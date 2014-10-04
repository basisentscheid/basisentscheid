<?
/**
 * email notification
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Notification {

	private $type;

	public $period;
	public $issues;
	public $issue;
	public $proposals;
	public $proposal;
	public $proponent;
	public $proponent_confirmed;
	public $proponent_confirming;
	public $argument;
	public $ballot;

	public static $default_settings = array(
		'all'         => array('new_proposal'=>true, 'submitted'=>true, 'admitted'=>true, 'debate'=>true, 'voting'=>true, 'finished'=>true),
		'ngroups'     => array('new_proposal'=>true, 'submitted'=>true, 'admitted'=>true, 'debate'=>true, 'voting'=>true, 'finished'=>true),
		'participant' => array('new_proposal'=>true, 'submitted'=>true, 'admitted'=>true, 'debate'=>true, 'voting'=>true, 'finished'=>true),
		'supporter'   => array('new_proposal'=>true, 'submitted'=>true, 'admitted'=>true, 'debate'=>true, 'voting'=>true, 'finished'=>true),
		'proponent'   => array('new_proposal'=>true, 'submitted'=>true, 'admitted'=>true, 'debate'=>true, 'voting'=>true, 'finished'=>true)
	);


	/**
	 *
	 * @param string  $type
	 */
	function __construct($type) {
		$this->type = $type;
	}


	/**
	 *
	 * @return array
	 */
	public static function interests() {
		return array(
			'all'         => _("all"),
			'ngroups'     => _("ngroups"),
			'participant' => _("area participant"),
			'supporter'   => _("supporter"),
			'proponent'   => _("proponent")
		);
	}


	/**
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'new_proposal' => _("new proposal"),
			'submitted'    => _("submitted"),
			'admitted'     => _("admitted"),
			'debate'       => _("debate"),
			'voting'       => _("voting"),
			'finished'     => _("finished")
		);
	}


	/**
	 * finally send the notifications
	 *
	 * @param array|null $recipients (optional) array of member IDs
	 */
	public function send($recipients=null) {

		$recipients = $this->recipients($recipients);

		// nobody to notify
		if (!$recipients) return;

		$headers = array();
		/*
		if (count($recipients) > 1) {
			$headers[] = "Bcc: ".join(", ", $recipients);
		} else {
			$to = $recipients[0];
		}
		*/
		$to = ERROR_MAIL; // for development

		list($subject, $body) = $this->content();

		send_mail($to, $subject, $body, $headers);

	}


	/**
	 * get mail addresses of the recipients
	 *
	 * @param array|null $recipients
	 * @return array
	 */
	private function recipients($recipients) {

		$sql = "SELECT DISTINCT mail FROM members ";

		if (is_array($recipients)) {
			if (!$recipients) return array();
			$sql .= "WHERE mail IS NOT NULL AND id IN (".join(",", $recipients).")";
		} else {

			$sql .= "
				JOIN notify               ON          notify.member = members.id
				LEFT JOIN members_ngroups ON members_ngroups.member = members.id
				LEFT JOIN supporters      ON      supporters.member = members.id
				WHERE members.mail IS NOT NULL
					AND notify.".$this->type."=TRUE
					AND ( notify.interest='all'";

			if ($this->period) {
				$sql .= "
						OR (notify.interest='ngroups'     AND members_ngroups.ngroup=".intval($this->period->id).")
						OR (notify.interest='participant' AND members_ngroups.ngroup=".intval($this->period->id)." AND members_ngroups.participant IS NOT NULL)";
			}

			$proposals = array();
			if ($this->issues) {
				foreach ($this->issues as $issue) {
					foreach ($issue->proposals() as $proposal) {
						$proposals[] = $proposal->id;
					}
				}
			} elseif ($this->issue) {
				foreach ($this->issue->proposals() as $proposal) {
					$proposals[] = $proposal->id;
				}
			} elseif ($this->proposal) {
				$proposals[] = $this->proposal->id;
			}
			if ($proposals) {
				$proposals = join(",", $proposals);
				$sql .= "
						OR (notify.interest='supporter' AND supporters.proposal IN (".$proposals."))
						OR (notify.interest='proponent' AND supporters.proposal IN (".$proposals.") AND supporters.proponent_confirmed=TRUE)";
			}

			$sql .= ")";

		}

		// don't notify a member about his own actions
		if (Login::$member) {
			$sql .= " AND members.id != ".intval(Login::$member->id);
		}

		return DB::fetchfieldarray($sql);
	}


	/**
	 * compose subject and body
	 *
	 * @return array
	 */
	private function content() {

		// ngroup
		if ($this->period) {
			$ngroup = $this->period->ngroup();
		} elseif ($this->issue) {
			$ngroup = $this->issue->area()->ngroup();
		} elseif ($this->proposal) {
			$ngroup = $this->proposal->issue()->area()->ngroup();
		}
		$body = _("Group").": ".$ngroup->name."\n\n";

		$separator = "-----8<--------------------------------------------------------------------\n"; // 75 characters

		switch ($this->type) {
		case "new_proposal":

			$subject = sprintf(_("New proposal %d in area %s"), $this->proposal->id, $this->proposal->issue()->area()->name);

			$body .= sprintf(_("Proponent '%s' added a new proposal:"), $this->proponent)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				."===== "._("Title")." =====\n"
				.mb_wordwrap($this->proposal->title)."\n\n"
				."===== "._("Content")." =====\n"
				.mb_wordwrap($this->proposal->content)."\n\n"
				."===== "._("Reason")." =====\n"
				.mb_wordwrap($this->proposal->reason)."\n";

			break;
		case "submitted":

			$subject = sprintf(_("Proposal %d submitted"), $this->proposal->id);

			$body .= sprintf(_("Proponent '%s' submitted this proposal:"), $this->proponent)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				."===== "._("Title")." =====\n"
				.mb_wordwrap($this->proposal->title)."\n\n"
				."===== "._("Content")." =====\n"
				.mb_wordwrap($this->proposal->content)."\n\n"
				."===== "._("Reason")." =====\n"
				.mb_wordwrap($this->proposal->reason)."\n";

			break;
		case "apply_proponent":

			$subject = sprintf(_("New proponent for proposal %d"), $this->proposal->id);

			$body .= mb_wordwrap(_("Proposal")." ".$this->proposal->id.": ".$this->proposal->title)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The following member asks to become proponent:")."\n\n"
				.$this->proponent."\n"
				.Login::$member->identity()."\n";

			break;
		case "confirmed_proponent":

			$subject = sprintf(_("Proponent confirmed for proposal %d"), $this->proposal->id);

			$body .= mb_wordwrap(_("Proposal")." ".$this->proposal->id.": ".$this->proposal->title)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The proponent ...")."\n\n"
				.$this->proponent_confirmed."\n\n"
				._("... has been confirmed by:")."\n\n"
				.$this->proponent_confirming;

			break;
		case "removed_proponent":

			$subject = sprintf(_("Proponent removed himself from proposal %d"), $this->proposal->id);

			$body .= mb_wordwrap(_("Proposal")." ".$this->proposal->id.": ".$this->proposal->title)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The following proponent removed himself:")."\n\n"
				.$this->proponent."\n";

			break;
		case "argument":

			$subject = sprintf(_("New reply to your argument in proposal %d"), $this->proposal->id);

			$body .= mb_wordwrap(_("Proposal")." ".$this->proposal->id.": ".$this->proposal->title)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				.sprintf(_("Member '%s' replied to your argument:"), Login::$member->username())."\n"
				.$separator
				.mb_wordwrap($this->argument->title)."\n\n"
				.mb_wordwrap($this->argument->content)."\n"
				.$separator
				._("Reply:")." ".BASE_URL."proposal.php?id=".$this->proposal->id."&argument_parent=".$this->argument->id."#form";

			break;
		case "admitted":

			$ids = array();
			foreach ( $this->proposals as $proposal ) {
				$ids[] = $proposal->id;
				$body .= mb_wordwrap(_("Proposal")." ".$proposal->id.": ".$proposal->title)."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}

			if (count($ids) > 1) {
				$subject = sprintf(_("Proposals %s admitted"), join(", ", $ids));
				$body = _("The following proposals have been admitted").":\n\n".$body;
			} else {
				$subject = sprintf(_("Proposal %d admitted"), $ids[0]);
				$body = _("The following proposal has been admitted").":\n\n".$body;
			}

			break;
		case "debate":

			$subject = sprintf(_("Debate started in period %d"), $this->period->id);

			$body .= "Debate has started on the following proposals:\n";

			foreach ( $this->issues as $issue ) {
				$body .= "\n";
				foreach ( $issue->proposals() as $proposal ) {
					$body .= mb_wordwrap(_("Proposal")." ".$proposal->id.": ".$proposal->title)."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n";
				}
			}

			$body .= "\n"._("Voting preparation").": ".datetimeformat($this->period->preparation)."\n"
				._("Voting").": ".datetimeformat($this->period->voting)."\n";

			break;
		case "voting":

			$subject = sprintf(_("Voting started in period %d"), $this->period->id);

			$body .= "Voting has started on the following proposals:\n";

			foreach ( $this->issues as $issue ) {
				$body .= "\n";
				foreach ( $issue->proposals() as $proposal ) {
					$body .= mb_wordwrap(_("Proposal")." ".$proposal->id.": ".$proposal->title)."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n";
				}
				$body .= "Vote: ".BASE_URL."vote.php?issue=".$issue->id."\n";
			}

			$body .= "\n"._("Voting end").": ".datetimeformat($this->period->counting)."\n";

			break;
		case "finished":

			// TODO, voting result download interface needed



			break;
		case "ballot_approved":

			$subject = sprintf(_("Ballot approved in period %d"), $this->period->id);

			$body .= _("Your ballot application has been approved:")."\n\n"
				.$this->ballot->description_for_mail();

			break;
		case "ballot_not_approved":

			$subject = sprintf(_("Ballot not approved in period %d"), $this->period->id);

			$body .= _("Your ballot application has NOT been approved:")."\n\n"
				.$this->ballot->description_for_mail();

			break;
		case "ballot_assigned":

			$subject = sprintf(_("Ballot assigned in period %d"), $this->period->id);

			$body .= mb_wordwrap(_("Ballot assignment has been started. You have been assigned to the following ballot:"))."\n\n"
				.$this->ballot->description_for_mail()."\n"
				.mb_wordwrap(sprintf(
					_("This ballot has been selected, either because you selected it yourself and it was approved or because it looks like it's the nearest one to where you live. You can change the selected ballot here until ballot preparation starts at %s:"),
					datetimeformat($this->period->ballot_preparation)
				))."\n"
				.BASE_URL."ballots.php?period=".$this->period->id;

			break;
		}

		return array($subject, $body);
	}


}
