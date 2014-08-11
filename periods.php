<?
/**
 * proposals.php
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


require "inc/common.php";

$d = new DbTableAdmin_Period("Period");
$d->dbtable = "periods";
$d->columns = array(
	array("id", _("No."), "right", "", false),
	array("debate",             _("Debate"),                 "", "timestamp", "timestamp"), // 4 weeks before counting
	array("preparation",        _("Voting preparation"),     "", "timestamp", "timestamp"), // 1 week before voting
	array("voting",             _("Online voting"),          "", "timestamp", "timestamp"), // 2 weeks before counting
	array("ballot_assignment",  _("Ballot assignment"),      "", "timestamp", "timestamp", 'null'=>true), // 3 weeks before counting
	array("ballot_preparation", _("Ballot preparation"),     "", "timestamp", "timestamp", 'null'=>true), // 1 week before counting
	array("counting",           _("Counting/End of period"), "", "timestamp", "timestamp"), // "Stichtag"
	array("online", _("Online"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array("secret", _("Secret"), "center", "boolean", "boolean", 'type'=>"boolean"),
	array(false, _("Ballots"), "center", "ballots", false)
);
$d->enable_filter = false;

$d->reference_check = array(
	"SELECT id FROM issues  WHERE period=%d",
	"SELECT id FROM ballots WHERE period=%d"
);

$d->msg_add_record          = _("New period");
$d->msg_edit_record         = _("Edit period %id%");
$d->msg_record_saved        = _("The new period %id% has been saved.");
$d->msg_really_delete       = _("Do you really want to delete the period %id%?");
$d->msg_record_deleted      = _("The period %id% has been deleted.");
$d->msg_record              = _("Period");
$d->msg_no_record_available = _("no period available for this view");
$d->pager->msg_itemsperpage = _("Periods per page");

if (Login::$admin) {
	$d->action($action);
} else {
	$d->enable_insert = false;
	$d->enable_edit   = false;
	$d->enable_delete_single = false;
}

html_head(_("Periods"));

$help = <<<HELP
Eine Abstimmungsperiode ist ein Zeitraum, in dem eine Liste von Anträgen erst debattiert und dann abgestimmt wird.

Bevor ein Abstimmungsperiode beginnt, ordnen die Verantwortlichen die zugelassenen Anträge der nächsten passenden Abstimmungsperiode zu. Zum festgelegten Zeitpunkt beginnt dann die Debatte. Während der Debatte kann bei jedem Antrag eine Urnenabstimmung gefordert werden. Wenn bis zur Abstimmungsvorbereitung das Quorum zur Urnenabstimmung erreicht ist, findet zu diesem Antrag eine Urnenabstimmung statt.

Parallel zur Debatte, Abstimmungsvorbereitung und Online-Abstimmung findet die Vorbereitung der Urnenabstimmung statt. Zunächst können Urnenanträge gestellt und die vorgeschlagenen Urnen von Teilnehmern für ihre Abstimmung ausgewählt werden. Bei ausreichender Teilnehmerzahl genehmigen die Verantworlichen die Urnen bis spätestens zum Zeitpunkt "Urnenzuordnung", an dem alle verbleibenden Teilnehmer den genehmigten Urnen entsprechend ihrem Wohnort zugeordnet werden. Bis zum Zeitpunkt der "Urnenvorbereitung" können die Teilnehmer noch eine andere Urne auswählen.

Die Online-Abstimmung beginnt zum festgesetzten Zeitpunkt und endet mit dem Zeitpunkt der Auszählung am Stichtag. Die Urnenabstimmung findet nur am Stichtag zu den bei der Urne angegebenen Öffnungszeiten statt.

Nicht bei jeder Abstimmungsperiode werden sowohl Online- als auch Urnenabstimmung durchgeführt. Falls erforderlich werden dann alle Anträge per Urne abgestimmt oder die für Urnenabstimmung vorgesehenen Anträge auf eine spätere Abstimmungsperiode verschoben.
HELP;
help($help);

$d->display();

html_foot();
