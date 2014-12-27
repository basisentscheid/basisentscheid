<?
/**
 * email notification
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


class Notification {

	private $type;

	// content for the notifications
	/** @var  Period $period */
	public $period;
	public $issues;
	/** @var  Issue $issue */
	public $issue;
	/** @var  Issue $issue */
	public $issue_old;
	public $proposals;
	/** @var  Proposal $proposal */
	public $proposal;
	public $proponent;
	public $proponent_confirmed;
	public $proponent_confirming;
	public $comment;
	/** @var  Ballot $ballot */
	public $ballot;

	// vote tokens
	public $personal_tokens;
	public $all_tokens;

	public static $default_settings = array(
		'all'         => array('comment'=>false, 'new_proposal'=>false, 'submitted'=>false, 'admitted'=>false, 'debate'=>false, 'finished'=>false),
		'ngroups'     => array('comment'=>false, 'new_proposal'=>true,  'submitted'=>true,  'admitted'=>true,  'debate'=>true,  'finished'=>true),
		'participant' => array('comment'=>false, 'new_proposal'=>true,  'submitted'=>true,  'admitted'=>true,  'debate'=>true,  'finished'=>true),
		'supporter'   => array('comment'=>false, 'new_proposal'=>true,  'submitted'=>true,  'admitted'=>true,  'debate'=>true,  'finished'=>true),
		'proponent'   => array('comment'=>true,  'new_proposal'=>true,  'submitted'=>true,  'admitted'=>true,  'debate'=>true,  'finished'=>true)
	);


	/**
	 *
	 * @param string  $type
	 */
	function __construct($type) {
		$this->type = $type;
	}


	/**
	 * for translation
	 *
	 * @return array
	 */
	public static function interests() {
		return array(
			'all'         => _("all proposals"),
			'ngroups'     => _("proposals in my ngroups"),
			'participant' => _("proposals in areas where I'm participant"),
			'supporter'   => _("proposals which I support"),
			'proponent'   => _("proposals where I'm proponent")
		);
	}


	/**
	 * for translation
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'comment'      => _("new comment"),
			'new_proposal' => _("new proposal"),
			'submitted'    => _("submitted"),
			'admitted'     => _("admitted"),
			'debate'       => _("debate"),
			// voting start notifications are sent to all entitled members of the group
			// vote receipts are sent individually
			'finished'     => _("finished")
		);
	}


	/**
	 * finally send the notifications
	 *
	 * @param array   $members (optional) array of member IDs
	 * @param array   $exclude (optional) array of member IDs to send no mail
	 * @return bool
	 */
	public function send($members=array(), $exclude=array()) {

		$recipients = $this->recipients($members, $exclude);

		$to = "";
		$bcc = array();
		switch ( count($recipients) ) {
		case 0:
			break;
		case 1:
			$to = $recipients[0];
			break;
		default:
			$bcc = $recipients;
		}

		if (NOTIFICATION_BCC) $bcc[] = trim(NOTIFICATION_BCC);

		// nobody to notify
		if (!$to and !$bcc) return;

		if (PHP_SAPI=="cli" and DEBUG) {
			echo "Send ".$this->type." notification to ".count($recipients)." recipients\n";
		}

		$headers = array();
		if ($bcc) $headers[] = "Bcc: ".join(", ", $bcc);

		list($subject, $body) = $this->content();

		return send_mail($to, $subject, $body, $headers);
	}


	/**
	 * get mail addresses of the recipients
	 *
	 * @param array   $members array of member IDs
	 * @param array   $exclude array of member IDs to send no mail
	 * @return array
	 */
	private function recipients($members, $exclude) {

		$sql = "SELECT DISTINCT mail FROM member";
		$or = array();

		// specified members
		if ( $members ) {
			$or[] = "member.id IN (".join(",", $members).")";
		}

		// members who enabled notification settings
		if ( isset(self::$default_settings['all'][$this->type]) ) {
			$sql .= "
				LEFT JOIN notify        ON        notify.member = member.id
				LEFT JOIN member_ngroup ON member_ngroup.member = member.id
				LEFT JOIN participant   ON   participant.member = member.id
				LEFT JOIN supporter     ON     supporter.member = member.id";

			$where_notify = "
					notify.".$this->type."=TRUE
					AND (
						notify.interest='all'";

			if ($this->period) {
				$where_notify .= "
						OR (notify.interest='ngroups' AND member_ngroup.ngroup=".intval($this->period->id).")";
			}

			$areas     = array();
			$proposals = array();
			if ($this->issues) {
				foreach ($this->issues as $issue) {
					/** @var $issue Issue */
					$areas[] = $issue->area;
					foreach ($issue->proposals() as $proposal) {
						$proposals[] = $proposal->id;
					}
				}
			} elseif ($this->issue) {
				$areas[] = $this->issue->area;
				foreach ($this->issue->proposals() as $proposal) {
					$proposals[] = $proposal->id;
				}
			} elseif ($this->proposal) {
				$areas[] = $this->proposal->issue()->area;
				$proposals[] = $this->proposal->id;
			}

			if ($areas) {
				$areas = join(",", array_unique($areas));
				$where_notify .= "
						OR (notify.interest='participant' AND participant.area IN (".$areas."))";
			}

			if ($proposals) {
				$proposals = join(",", $proposals);
				$where_notify .= "
						OR (notify.interest='supporter' AND supporter.proposal IN (".$proposals."))
						OR (notify.interest='proponent' AND supporter.proposal IN (".$proposals.") AND supporter.proponent_confirmed=TRUE)";
			}

			$where_notify .= "
					)";

			$or[] = "(".$where_notify.")";
		}

		if (!$or) return array();
		$sql .= "
				WHERE member.mail IS NOT NULL
					AND (".join(" OR ", $or).")";

		// don't notify a member about his own actions
		if (Login::$member) $exclude[] = intval(Login::$member->id);
		if ($exclude) {
			$sql .= "
					AND member.id NOT IN (".join(", ", $exclude).")";
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
		} else {
			trigger_error("ngroup could not be determined", E_USER_WARNING);
			$ngroup = null;
		}
		$body = _("Group").": ".$ngroup->name."\n\n";

		$separator = "-----8<--------------------------------------------------------------------\n"; // 75 characters

		switch ($this->type) {
		case "comment":

			$subject = sprintf(_("New comment in proposal %d"), $this->proposal->id);

			$uri = BASE_URL."proposal.php?id=".$this->proposal->id;
			if ($this->comment->rubric == "discussion") $uri .= "&discussion=1";

			$body .= _("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n\n";
			if (Login::$member) {
				$body .= sprintf(_("Member '%s' posted this comment:"), Login::$member->username());
			} else {
				$body .= _("Someone not logged in posted this comment:");
			}
			$body .= "\n"
				.$uri."&comment=".$this->comment->id."\n"
				.$separator
				.$this->comment->title."\n\n"
				.$this->comment->content."\n"
				.$separator
				._("Reply:")."\n"
				.$uri."&reply=".$this->comment->id;

			break;
		case "reply":

			$subject = sprintf(_("New reply to your comment in proposal %d"), $this->proposal->id);

			$uri = BASE_URL."proposal.php?id=".$this->proposal->id;
			if ($this->comment->rubric == "discussion") $uri .= "&discussion=1";

			$body .= _("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n\n";
			if (Login::$member) {
				$body .= sprintf(_("Member '%s' replied to your comment:"), Login::$member->username());
			} else {
				$body .= _("Someone not logged in replied to your comment:");
			}
			$body .= "\n"
				.$uri."&comment=".$this->comment->id."\n"
				.$separator
				.$this->comment->title."\n\n"
				.$this->comment->content."\n"
				.$separator
				._("Reply:")."\n"
				.$uri."&reply=".$this->comment->id;

			break;
		case "new_proposal":

			$subject = sprintf(_("New proposal %d in area %s"), $this->proposal->id, $this->proposal->issue()->area()->name);

			$body .= sprintf(_("Proponent '%s' added a new proposal:"), $this->proponent)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				."===== "._("Title")." =====\n"
				.$this->proposal->title."\n\n"
				."===== "._("Content")." =====\n"
				.$this->proposal->content."\n\n"
				."===== "._("Reason")." =====\n"
				.$this->proposal->reason."\n";

			break;
		case "submitted":

			$subject = sprintf(_("Proposal %d submitted"), $this->proposal->id);

			$body .= sprintf(_("Proponent '%s' submitted this proposal:"), $this->proponent)."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				."===== "._("Title")." =====\n"
				.$this->proposal->title."\n\n"
				."===== "._("Content")." =====\n"
				.$this->proposal->content."\n\n"
				."===== "._("Reason")." =====\n"
				.$this->proposal->reason."\n";

			break;
		case "apply_proponent":

			$subject = sprintf(_("New proponent for proposal %d"), $this->proposal->id);

			$body .= _("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The following member asks to become proponent:")."\n\n"
				.$this->proponent."\n"
				.Login::$member->identity()."\n";

			break;
		case "confirmed_proponent":

			$subject = sprintf(_("Proponent confirmed for proposal %d"), $this->proposal->id);

			$body .= _("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The proponent ...")."\n\n"
				.$this->proponent_confirmed."\n\n"
				._("... has been confirmed by:")."\n\n"
				.$this->proponent_confirming;

			break;
		case "removed_proponent":

			$subject = sprintf(_("Proponent removed himself from proposal %d"), $this->proposal->id);

			$body .= _("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				._("The following proponent removed himself:")."\n\n"
				.$this->proponent."\n";

			break;
		case "admitted":

			if (count($this->proposals) > 1) {
				$body .= _("The following proposals have been admitted").":\n\n";
			} else {
				$body .= _("The following proposal has been admitted").":\n\n";
			}

			$ids = array();
			foreach ( $this->proposals as $proposal ) {
				$ids[] = $proposal->id;
				$body .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}

			if (count($ids) > 1) {
				$subject = sprintf(_("Proposals %s admitted"), join(", ", $ids));
			} else {
				$subject = sprintf(_("Proposal %d admitted"), $ids[0]);
			}

			break;
		case "debate":

			$subject = sprintf(_("Debate started in period %d"), $this->period->id);

			$body .= _("Debate has started on the following proposals").":\n";

			foreach ( $this->issues as $issue ) {
				/** @var $issue Issue */
				$body .= "\n";
				foreach ( $issue->proposals() as $proposal ) {
					$body .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n";
				}
			}

			$body .= "\n"._("Voting preparation").": ".datetimeformat($this->period->preparation)."\n"
				._("Voting").": ".datetimeformat($this->period->voting)."\n";

			break;
		case "finished":

			$subject = sprintf(_("Voting finished in period %d"), $this->period->id);

			$body .= _("Voting has finished on the following proposals").":\n";

			foreach ( $this->issues as $issue ) {
				/** @var $issue Issue */
				$body .= "\n";
				$proposals = $issue->proposals(true);
				foreach ( $proposals as $proposal ) {
					$body .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
						.BASE_URL."proposal.php?id=".$proposal->id."\n".
						_("Yes").": ".$proposal->yes.", "._("No").": ".$proposal->no.", "._("Abstention").": ".$proposal->abstention;
					if (count($proposals) > 1) {
						$body .= ", "._("Score").": ".$proposal->score;
					}
					$body .= "\n";
				}
				$body .= _("Vote result").": ".BASE_URL."vote_result.php?issue=".$issue->id."\n";
			}

			break;
		case "proposal_moved":

			$subject = sprintf(_("Proposal %d moved to a different issue"), $this->proposal->id);

			$body .= sprintf(_("An administrator moved the following proposal from issue %d to issue %d:"), $this->issue_old->id, $this->issue->id)."\n"
				._("Proposal")." ".$this->proposal->id.": ".$this->proposal->title."\n"
				.BASE_URL."proposal.php?id=".$this->proposal->id."\n\n"
				.sprintf(_("Proposals in the old issue %d:"), $this->issue_old->id)."\n";
			foreach ( $this->issue_old->proposals() as $proposal ) {
				$body .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}
			$body .= "\n"
				.sprintf(_("Other proposals in the new issue %d:"), $this->issue->id)."\n";
			foreach ( $this->issue->proposals() as $proposal ) {
				if ($proposal->id == $this->proposal->id) continue; // skip the moved proposal
				$body .= _("Proposal")." ".$proposal->id.": ".$proposal->title."\n"
					.BASE_URL."proposal.php?id=".$proposal->id."\n";
			}
			$body .= "\n"._("Notice that if you demanded offline voting for the old issue, this was not automatically transferred to the new issue. If you still want offline voting, you should demand it again on the new issue!")."\n";

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

			$body .= _("Ballot assignment has been started. You have been assigned to the following ballot:")."\n\n"
				.$this->ballot->description_for_mail()."\n"
				.sprintf(_("This ballot has been selected, either because you selected it yourself and it was approved or because it looks like it's the nearest one to where you live. You can change the selected ballot here until ballot preparation starts at %s:"), datetimeformat($this->period->ballot_preparation))."\n"
				.BASE_URL."ballots.php?period=".$this->period->id;

			break;
		default:
			trigger_error("unknown notification type", E_USER_WARNING);
			$subject = null;
		}

		// remove HTML line break hints
		$body = strtr($body, array("&shy;"=>""));

		return array($subject, $body);
	}


}
