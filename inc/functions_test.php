<?
/**
 * functions for tests
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


/**
 *
 * @param Issue   $issue
 */
function random_votes(Issue $issue) {

	$proposals = $issue->proposals(true);
	$acceptance_array = array();
	$score_array = array();
	foreach ( $proposals as $proposal ) {
		$part = rand(1, 10);
		$acceptance_array[$proposal->id] = array_merge(
			array_fill(1, rand(1, 10),     -1),
			array_fill(1, pow($part,    2), 0),
			array_fill(1, pow(11-$part, 2), 1)
		);
		if (count($proposals) > 1) {
			$score_array[$proposal->id] = array_merge(
				array_fill(1, pow(rand(1, 12), 2), 0),
				array_fill(1, pow(rand(1,  3), 2), 1),
				array_fill(1, pow(rand(1,  3), 2), 2),
				array_fill(1, pow(rand(1, 12), 2), 3)
			);
		}
	}
	$sql = "SELECT * FROM members
 		JOIN members_ngroups ON members.id = members_ngroups.member
 		JOIN vote_tokens ON vote_tokens.member = members.id AND vote_tokens.issue = ".intval($issue->id)."
		WHERE members_ngroups.ngroup = ".$issue->area()->ngroup." AND members.entitled = TRUE
		LIMIT ".rand(10, 100);
	$result = DB::query($sql);
	while ( Login::$member = DB::fetch_object($result, "Member") ) {
		$vote = array();
		foreach ( $proposals as $proposal ) {
			$vote[$proposal->id]['acceptance'] = $acceptance_array[$proposal->id][ array_rand($acceptance_array[$proposal->id]) ];
			if (count($proposals) > 1) {
				$vote[$proposal->id]['score'] = $score_array[$proposal->id][ array_rand($score_array[$proposal->id]) ];
			}
		}
		$token = $issue->vote_token();
		$issue->vote($token, $vote);
	}

}
