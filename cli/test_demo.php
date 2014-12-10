#!/usr/bin/php
<?
/**
 * generate demo data (in German)
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


if ( $dir = dirname($_SERVER['argv'][0]) ) chdir($dir);
define('DOCROOT', "../");
require DOCROOT."inc/common_cli.php";

require DOCROOT."inc/functions_test.php";


$ngroup = new_ngroup("Beispielgliederung", 200);

// create area
$area = new Area;
$area->ngroup = $ngroup->id;
$area->name = "Politik";
$area->create();
$area2 = new Area;
$area2->ngroup = $ngroup->id;
$area2->name = "Innerparteiliches";
$area2->create();

$blindtext = "Auch gibt es niemanden, der den Schmerz an sich liebt, sucht oder wünscht, nur, weil er Schmerz ist, es sei denn, es kommt zu zufälligen Umständen, in denen Mühen und Schmerz ihm große Freude bereiten können. Um ein triviales Beispiel zu nehmen, wer von uns unterzieht sich je anstrengender körperlicher Betätigung, außer um Vorteile daraus zu ziehen? Aber wer hat irgend ein Recht, einen Menschen zu tadeln, der die Entscheidung trifft, eine Freude zu genießen, die keine unangenehmen Folgen hat, oder einen, der Schmerz vermeidet, welcher keine daraus resultierende Freude nach sich zieht?";

$password = crypt("test");


// period in finished state

$sql = "INSERT INTO periods (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now() - interval '4 weeks',
		now() - interval '2 weeks',
		now() - interval '1 week',
		now(),
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

create_vote_proposals($period);

cron();
cron();
cron();

random_votes($issue1);
random_votes($issue2);

cron();


// period in voting state

$sql = "INSERT INTO periods (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now() - interval '2 weeks',
		now() - interval '3 days',
		now(),
		now() + interval '2 weeks',
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

create_vote_proposals($period);

cron();
cron();
cron();


// period in debate state

login(1);
$proposal10 = new Proposal;
$proposal10->title = "Beispielantrag in der Debatte";
$proposal10->content = $blindtext."\n\n".$blindtext;
$proposal10->reason = $blindtext;
$proposal10->create(Login::$member->username, $area->id);

for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal10->add_proponent(Login::$member->username, true);
}

$proposal10->submit();
$proposal10->read();

for ( $i=6; $i<=25; $i++ ) {
	login($i);
	$proposal10->add_support();
}

$sql = "INSERT INTO periods (debate, preparation, voting, counting, ballot_voting, ngroup)
	VALUES (
		now(),
		now() + interval '2 weeks',
		now() + interval '3 weeks',
		now() + interval '4 weeks',
		true,
		".$ngroup->id."
	) RETURNING id";
$result = DB::query($sql);
$row = DB::fetch_row($result);
$period = $row[0];

$issue10 = $proposal10->issue();
// assign issue to period
$issue10->period = $period;
/** @var $issue Issue */
$issue10->update(['period']);

cron();


// proposals without period

login(0);
$proposal = new Proposal;
$proposal->title = "Neuer Beispielantrag";
$proposal->content = $blindtext."\n\n".$blindtext;
$proposal->reason = $blindtext;
$proposal->create(Login::$member->username, $area->id);

login(0);
$proposal = new Proposal;
$proposal->title = "abgebrochener Beispielantrag";
$proposal->content = $blindtext."\n\n".$blindtext;
$proposal->reason = $blindtext;
$proposal->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal->add_proponent(Login::$member->username, true);
}
$proposal->submit();
// time warp the proposal in the past
DB::query("UPDATE proposals SET submitted = now() - interval '7 months' WHERE id=".intval($proposal->id));
$proposal->read();
cron();

login(1);
$proposal2 = new Proposal;
$proposal2->title = "zugelassener Beispielantrag";
$proposal2->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal2->reason = $blindtext."\n\n".$blindtext;
$proposal2->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal2->add_proponent(Login::$member->username, true);
}
$proposal2->submit();
$proposal2->read();
for ( $i=6; $i<=21; $i++ ) {
	login($i);
	$proposal2->add_support();
}

login(1);
$proposal3 = new Proposal;
$proposal3->title = "zugelassener Alternativantrag";
$proposal3->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal3->reason = $blindtext."\n\n".$blindtext;
$proposal3->issue = $proposal2->issue;
$proposal3->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal3->add_proponent(Login::$member->username, true);
}
$proposal3->submit();
$proposal3->read();
for ( $i=6; $i<=12; $i++ ) {
	login($i);
	$proposal3->add_support();
}

login(1);
$proposal4 = new Proposal;
$proposal4->title = "eingereichter Antrag";
$proposal4->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal4->reason = $blindtext."\n\n".$blindtext;
$proposal4->issue = $proposal2->issue;
$proposal4->create(Login::$member->username, $area->id);
for ( $i=2; $i<=5; $i++ ) {
	login($i);
	$proposal4->add_proponent(Login::$member->username, true);
}
$proposal4->submit();

login(1);
$proposal5 = new Proposal;
$proposal5->title = "noch nicht eingereichter Antrag";
$proposal5->content = $blindtext."\n\n".$blindtext."\n\n".$blindtext;
$proposal5->reason = $blindtext."\n\n".$blindtext;
$proposal5->issue = $proposal2->issue;
$proposal5->create(Login::$member->username, $area->id);


/**
 *
 * @param integer $period
 */
function create_vote_proposals($period) {
	global $area, $blindtext, $proposal1, $proposal2, $proposal3, $issue1, $issue2;

	// single proposal
	login(1);
	$proposal1 = new Proposal;
	$proposal1->title = "einzelner Beispielantrag";
	$proposal1->content = $blindtext."\n\n".$blindtext;
	$proposal1->reason = $blindtext;
	$proposal1->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal1->add_proponent(Login::$member->username, true);
	}
	$proposal1->submit();
	$proposal1->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal1->add_support();
	}

	$issue1 = $proposal1->issue();
	// assign issue to period
	$issue1->period = $period;
	/** @var $issue Issue */
	$issue1->update(['period']);

	// three proposals
	login(1);
	$proposal2 = new Proposal;
	$proposal2->title = "Beispielantrag";
	$proposal2->content = $blindtext."\n\n".$blindtext;
	$proposal2->reason = $blindtext;
	$proposal2->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal2->add_proponent(Login::$member->username, true);
	}
	$proposal2->submit();
	$proposal2->read();
	for ( $i=6; $i<=24; $i++ ) {
		login($i);
		$proposal2->add_support();
	}

	login(1);
	$proposal3 = new Proposal;
	$proposal3->title = "Alternativantrag";
	$proposal3->content = $blindtext."\n\n".$blindtext;
	$proposal3->reason = $blindtext;
	$proposal3->issue = $proposal2->issue;
	$proposal3->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal3->add_proponent(Login::$member->username, true);
	}
	$proposal3->submit();
	$proposal3->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal3->add_support();
	}

	login(1);
	$proposal4 = new Proposal;
	$proposal4->title = "nicht zugelassener Alternativantrag";
	$proposal4->content = $blindtext."\n\n".$blindtext;
	$proposal4->reason = $blindtext;
	$proposal4->issue = $proposal2->issue;
	$proposal4->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal4->add_proponent(Login::$member->username, true);
	}
	$proposal4->submit();
	$proposal4->read();
	for ( $i=6; $i<=8; $i++ ) {
		login($i);
		$proposal4->add_support();
	}

	login(1);
	$proposal5 = new Proposal;
	$proposal5->title = "zurückgezogener Antrag";
	$proposal5->content = $blindtext."\n\n".$blindtext;
	$proposal5->reason = $blindtext;
	$proposal5->issue = $proposal2->issue;
	$proposal5->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal5->add_proponent(Login::$member->username, true);
	}
	$proposal5->submit();
	$proposal5->read();
	for ( $i=6; $i<=15; $i++ ) {
		login($i);
		$proposal5->add_support();
	}
	// revoke by removing all proponents
	for ( $i=1; $i<=5; $i++ ) {
		login($i);
		$proposal5->remove_proponent(Login::$member);
	}

	$issue2 = $proposal2->issue();
	// assign issue to period
	$issue2->period = $period;
	/** @var $issue Issue */
	$issue2->update(['period']);

	// single proposal for offline voting
	login(1);
	$proposal6 = new Proposal;
	$proposal6->title = "einzelner Beispielantrag";
	$proposal6->content = $blindtext."\n\n".$blindtext;
	$proposal6->reason = $blindtext;
	$proposal6->create(Login::$member->username, $area->id);
	for ( $i=2; $i<=5; $i++ ) {
		login($i);
		$proposal6->add_proponent(Login::$member->username, true);
	}
	$proposal6->submit();
	$proposal6->read();
	for ( $i=6; $i<=23; $i++ ) {
		login($i);
		$proposal6->add_support();
	}

	$issue3 = $proposal6->issue();

	for ( $i=1; $i<=23; $i++ ) {
		login($i);
		$issue3->demand_votingmode();
	}

	// assign issue to period
	$issue3->period = $period;
	/** @var $issue Issue */
	$issue3->update(['period']);

}


/**
 * create a member once
 *
 * @param integer $id
 */
function login($id) {
	global $password, $ngroup;

	static $members = array();
	if (isset($members[$id])) {
		Login::$member = $members[$id];
		return;
	}

	static $names;
	if (!$names) {
		$names = array(
			// top 500 of http://de.wiktionary.org/wiki/Verzeichnis:Deutsch/Liste_der_h%C3%A4ufigsten_m%C3%A4nnlichen_Vornamen_Deutschlands
			"Peter", "Wolfgang", "Michael", "Werner", "Klaus", "Thomas", "Manfred", "Helmut", "Jürgen", "Heinz", "Gerhard", "Andreas", "Hans", "Josef", "Günter", "Dieter", "Horst", "Walter", "Frank", "Bernd", "Karl", "Herbert", "Franz", "Martin", "Uwe", "Georg", "Heinrich", "Stefan", "Christian", "Karl-Heinz", "Rudolf", "Kurt", "Hermann", "Johann", "Wilhelm", "Siegfried", "Rolf", "Joachim", "Alfred", "Rainer", "Jörg", "Ralf", "Erich", "Norbert", "Bernhard", "Willi", "Alexander", "Ulrich", "Markus", "Matthias", "Harald", "Paul", "Roland", "Ernst", "Reinhard", "Günther", "Gerd", "Fritz", "Otto", "Friedrich", "Erwin", "Lothar", "Robert", "Dirk", "Johannes", "Volker", "Wilfried", "Richard", "Anton", "Jens", "Hans-Jürgen", "Hubert", "Udo", "Holger", "Albert", "Ludwig", "Dietmar", "Hartmut", "Reinhold", "Hans-Joachim", "Adolf", "Detlef", "Oliver", "Christoph", "Stephan", "Axel", "Reiner", "Alois", "Eberhard", "Waldemar", "Heiko", "Daniel", "Torsten", "Sven", "Bruno", "Olaf", "Mario", "Konrad", "Steffen", "Ingo", "Jochen", "Thorsten", "Max", "Alfons", "Rüdiger", "Carsten", "Viktor", "Hans-Peter", "Rudi", "Friedhelm", "Armin", "Jan", "Lutz", "Ewald", "Winfried", "Egon", "Erhard", "Sebastian", "Jakob", "Marco", "Harry", "Eduard", "Eugen", "Karlheinz", "Andre", "Klaus-Dieter", "Achim", "Karsten", "Edgar", "Claus", "Hans-Dieter", "Tobias", "Theo", "Mathias", "Gottfried", "Emil", "Guido", "Arno", "Marc", "Eckhard", "Marcus", "Gustav", "Florian", "Dietrich", "Theodor", "Berthold", "Burkhard", "Rene", "Artur", "August", "Edmund", "Arnold", "Franz-Josef", "Lars", "Patrick", "Willy", "Ferdinand", "Gerald", "Ralph", "Hans-Georg", "Fred", "Leo", "Bodo", "Hugo", "Philipp", "Klaus-Peter", "Ronald", "Maik", "Oskar", "Mike", "David", "Gert", "Roman", "Björn", "Wolfram", "Hans-Werner", "Arthur", "Gregor", "Henning", "Nikolaus", "Marcel", "Felix", "Elmar", "Edwin", "Gunter", "Heribert", "Leonhard", "Adam", "Raimund", "Manuel", "Albrecht", "Clemens", "Ludger", "Henry", "Heiner", "Ulf", "Timo", "Antonio", "Lorenz", "Benjamin", "Detlev", "Mark", "Dennis", "Oswald", "Simon", "Benno", "Engelbert", "Ronny", "Mehmet", "Xaver", "Jörn", "Ali", "Gerold", "Toni", "Helmuth", "Christof", "Sergej", "Volkmar", "Mirko", "Tim", "André", "Marko", "Gernot", "Boris", "Hans-Jörg", "Vladimir", "Mustafa", "Knut", "Willibald", "Dominik", "Hans-Ulrich", "Ottmar", "Hubertus", "Giuseppe", "Heinz-Dieter", "Otmar", "Heino", "Enrico", "Siegmund", "Julius", "Hendrik", "Hans-Günter", "Alwin", "Heinz-Jürgen", "René", "Hartwig", "Erik", "Nils", "Klemens", "Wladimir", "Helge", "Hans-Josef", "Ivan", "Roger", "Siegbert", "Hilmar", "Maximilian", "Falk", "Valentin", "Gunther", "Rupert", "Hans-Hermann", "Arne", "Ingolf", "Gunnar", "Karl", "Hermann-Josef", "Thilo", "Adalbert", "Alex", "Tino", "Andrej", "Salvatore", "Hans-Heinrich", "Nico", "Eric", "Konstantin", "Ahmet", "Joseph", "Hinrich", "Egbert", "Fabian", "Hasan", "Ekkehard", "Marian", "Hansjörg", "Christopher", "Francesco", "Hagen", "Silvio", "Wolf", "Siegmar", "Nikolai", "Klaus-Jürgen", "John", "Heinz-Peter", "Georgios", "Bert", "Benedikt", "Gisbert", "Adrian", "Leopold", "Karl-Josef", "Giovanni", "Eckart", "Igor", "Tilo", "Ibrahim", "Reimund", "Aloys", "Marek", "Carl", "Gebhard", "Ullrich", "Wolf-Dieter", "Juri", "Gotthard", "Hüseyin", "Hanni", "Roberto", "Bertram", "Henrik", "Hans-Gerd", "Hans", "Hannes", "Dimitrios", "Albin", "Hans", "Arnd", "Hans-Otto", "Arndt", "Victor", "Uli", "Friedrich-Wilhelm", "Sönke", "Horst-Dieter", "Hans-Günther", "Bastian", "Vincenzo", "Ismail", "Norman", "Heinz-Josef", "Karl-Friedrich", "Denis", "Marius", "Antonius", "Frieder", "Ansgar", "Angelo", "Tom", "Rolf-Dieter", "Harri", "Malte", "Rico", "Götz", "Reinhardt", "Hellmut", "Bernard", "Pascal", "Falko", "Fridolin", "Anatoli", "Milan", "Eckard", "Rafael", "Moritz", "Friedbert", "Murat", "Kai-Uwe", "Hardy", "Sören", "Hans-Martin", "Vitali", "Jose", "Heinz-Günter", "Luigi", "Julian", "Henryk", "Heinz-Werner", "Klaus", "Edward", "Burghard", "Ortwin", "Hans-Walter", "Christos", "Konstantinos", "Pierre", "Philip", "Kaspar", "Kevin", "Gabriel", "Leonid", "Hans-Wilhelm", "Friedemann", "Hanno", "Kuno", "Osman", "Niels", "Herwig", "Dierk", "Meinolf", "Emanuel", "Nikola", "Fredi", "Meinhard", "Carlo", "Claus-Dieter", "Guenter", "Cornelius", "Raphael", "Hanspeter", "Gero", "Andy", "Mohamed", "Torben", "Domenico", "Josip", "Hans-J.", "Diethelm", "Swen", "Eckehard", "Till", "Lukas", "Berndt", "Enno", "Nikolaos", "Hans", "Jens-Uwe", "Michel", "Franz", "Vinzenz", "Sigurd", "Nikolaj", "Stanislaw", "Gottlieb", "Andree", "Wulf", "Diethard", "Robin", "Carlos", "Veit", "Franco", "Heinz-Georg", "Nicolas", "Ivo", "Dimitri", "Danny", "Jonas", "Steven", "Andrzej", "Karl-Otto", "Walther", "Klaus", "Harro", "Janusz", "Hans-Christian", "Sandro", "Hans-Jochen", "Eckhardt", "Jost", "Karl-Wilhelm", "Yusuf", "Wenzel", "Bogdan", "Guiseppe", "Gilbert", "Ernst-August", "Ehrenfried", "Ignaz", "Wieland", "Hans", "Karl-Ludwig", "Karl-Ernst", "Hellmuth", "Darius", "Karl-Heinrich", "Magnus", "Helmar", "Metin", "Arnulf", "Mirco", "Juergen", "Miroslaw", "Sigmund", "Claus-Peter", "Claudio", "Pietro", "William", "Heinz-Joachim", "Jonas", "Halil", "Ramazan", "Ahmed", "Hanns", "Miroslav", "Piotr", "Peer", "Helfried", "Hans", "Samuel",
			// top 500 of http://de.wiktionary.org/wiki/Verzeichnis:Deutsch/Liste_der_h%C3%A4ufigsten_weiblichen_Vornamen_Deutschlands
			"Maria", "Ursula", "Monika", "Petra", "Elisabeth", "Sabine", "Renate", "Helga", "Karin", "Brigitte", "Ingrid", "Erika", "Andrea", "Gisela", "Claudia", "Susanne", "Gabriele", "Christa", "Christine", "Hildegard", "Anna", "Birgit", "Barbara", "Gertrud", "Heike", "Marianne", "Elke", "Martina", "Angelika", "Irmgard", "Inge", "Ute", "Elfriede", "Doris", "Marion", "Ruth", "Ulrike", "Hannelore", "Jutta", "Gerda", "Kerstin", "Ilse", "Anneliese", "Margarete", "Ingeborg", "Anja", "Edith", "Sandra", "Waltraud", "Beate", "Rita", "Katharina", "Christel", "Nicole", "Regina", "Eva", "Rosemarie", "Erna", "Manuela", "Sonja", "Johanna", "Irene", "Silke", "Gudrun", "Christiane", "Cornelia", "Tanja", "Anita", "Bettina", "Silvia", "Daniela", "Sigrid", "Simone", "Stefanie", "Annette", "Bärbel", "Michaela", "Angela", "Dagmar", "Heidi", "Annemarie", "Helene", "Anke", "Margot", "Sylvia", "Christina", "Katrin", "Melanie", "Hedwig", "Roswitha", "Martha", "Alexandra", "Else", "Iris", "Katja", "Charlotte", "Lieselotte", "Hilde", "Astrid", "Anni", "Margit", "Frieda", "Carmen", "Anne", "Ilona", "Luise", "Margret", "Dorothea", "Rosa", "Herta", "Olga", "Lydia", "Julia", "Marlies", "Yvonne", "Antje", "Käthe", "Kathrin", "Agnes", "Gerlinde", "Irma", "Vera", "Edeltraud", "Ines", "Stephanie", "Carola", "Franziska", "Heidrun", "Marina", "Britta", "Nadine", "Diana", "Ellen", "Elvira", "Sieglinde", "Gabi", "Emma", "Veronika", "Marita", "Theresia", "Bianca", "Klara", "Kirsten", "Magdalena", "Adelheid", "Annegret", "Brunhilde", "Nina", "Ina", "Hanna", "Uta", "Heidemarie", "Therese", "Gertraud", "Ramona", "Lisa", "Marga", "Paula", "Marlene", "Irina", "Berta", "Tatjana", "Verena", "Marie", "Anette", "Evelyn", "Jana", "Elena", "Elli", "Mathilde", "Eva-Maria", "Corinna", "Wilma", "Lore", "Eleonore", "Judith", "Sabrina", "Gaby", "Karola", "Liselotte", "Elsa", "Elsbeth", "Isolde", "Ida", "Heide", "Ella", "Tina", "Thea", "Regine", "Dora", "Sibylle", "Liane", "Kornelia", "Mechthild", "Waltraut", "Lotte", "Sybille", "Dorothee", "Josefine", "Annett", "Steffi", "Margarethe", "Gabriela", "Maren", "Patricia", "Marie-Luise", "Juliane", "Pia", "Valentina", "Hermine", "Lina", "Ingeburg", "Brigitta", "Jessica", "Karla", "Grete", "Frauke", "Helena", "Margrit", "Alice", "Magdalene", "Kristina", "Beatrix", "Jennifer", "Hella", "Miriam", "Walburga", "Friederike", "Margitta", "Marlis", "Natalie", "Eveline", "Nadja", "Sarah", "Emilie", "Meike", "Edelgard", "Emmi", "Doreen", "Ulla", "Sophie", "Ursel", "Esther", "Ilka", "Marta", "Viola", "Jasmin", "Helma", "Linda", "Mandy", "Natalia", "Edda", "Carolin", "Wilhelmine", "Annelies", "Hilda", "Maike", "Lilli", "Cordula", "Lucia", "Karen", "Hiltrud", "Lidia", "Tamara", "Annerose", "Caroline", "Margareta", "Irmtraud", "Edeltraut", "Käte", "Karina", "Antonia", "Susann", "Ottilie", "Evelin", "Jacqueline", "Viktoria", "Janine", "Annelie", "Hertha", "Liesel", "Rosi", "Inga", "Magda", "Grit", "Gunda", "Lucie", "Josefa", "Alma", "Karoline", "Meta", "Henriette", "Rosmarie", "Wiebke", "Uschi", "Birgitt", "Peggy", "Adele", "Natascha", "Elly", "Sigrun", "Elise", "Dörte", "Gertraude", "Kristin", "Jeanette", "Antonie", "Gretel", "Betty", "Isabel", "Lena", "Constanze", "Jenny", "Isabella", "Carina", "Gundula", "Elfi", "Rosel", "Janina", "Galina", "Gitta", "Susan", "Denise", "Traute", "Evi", "Beatrice", "Annika", "Anna-Maria", "Liesbeth", "Svetlana", "Wendelin", "Larissa", "Leni", "Swetlana", "Sofie", "Sylke", "Bianka", "Carla", "Resi", "Ludmilla", "Siegrid", "Pauline", "Frida", "Vanessa", "Rosina", "Dorit", "Anett", "Melitta", "Auguste", "Mareike", "Bernadette", "Annelore", "Ivonne", "Ria", "Irena", "Siglinde", "Laura", "Klaudia", "Babette", "Sofia", "Lisbeth", "Almut", "Susanna", "Wanda", "Svenja", "Giesela", "Emmy", "Kathleen", "Fatma", "Luzia", "Nelli", "Christl", "Ariane", "Jeannette", "Corina", "Saskia", "Rebecca", "Isabell", "Wally", "Hanne", "Thekla", "Karolina", "Felicitas", "Cäcilia", "Valeri", "Conny", "Gesine", "Selma", "Birte", "Marliese", "Luzie", "Hedi", "Romy", "Anny", "Agathe", "Nora", "Kirstin", "Cäcilie", "Ingelore", "Dana", "Teresa", "Marika", "Nancy", "Alwine", "Amalie", "Lilo", "Imke", "Margarita", "Johanne", "Henny", "Margaretha", "Maja", "Christin", "Rose", "Gertrude", "Anika", "Ricarda", "Mirjam", "Gerti", "Danuta", "Sabina", "Ayse", "Maritta", "Ludmila", "Sina", "Loni", "Isabelle", "Marija", "Theresa", "Nathalie", "Eugenie", "Sandy", "Sophia", "Monique", "Minna", "Marietta", "Leonore", "Ewa", "Reinhild", "Pamela", "Reinhilde", "Isa", "Gerta", "Mina", "Trude", "Henri", "Janet", "Natalja", "Kunigunde", "Wiltrud", "Sara", "Irmtraut", "Emine", "Gesa", "Änne", "Ortrud", "Heiderose", "Hatice", "Silvana", "Ana", "Mona", "Birgitta", "Brunhild", "Ernestine", "Aloisia", "Lilly", "Halina", "Valerie", "Konstanze", "Monica", "Joanna", "Emilia", "Rosalinde", "Katarina", "Evelyne", "Irmhild", "Cindy", "Kati", "Catrin", "Inka", "Rosita", "Jaqueline", "Dunja", "Simona", "Madeleine", "Jolanta", "Dietlinde", "Helen", "Gerhild", "Cathrin", "Inna", "Marlen", "Krystyna", "Manja", "Patrizia", "Centa", "Traudel", "Wera", "Anastasia", "Renata"
		);
		shuffle($names);
	}

	Login::$member = new Member;
	Login::$member->invite = Login::generate_token(24);
	Login::$member->entitled = true;
	Login::$member->create();
	set_unique_username(Login::$member, $names[$id]);
	Login::$member->password = $password;
	$update_fields = array('username', 'password', 'entitled');
	Login::$member->update($update_fields, 'activated=now()');
	DB::insert("members_ngroups", array('member'=>Login::$member->id, 'ngroup'=>$ngroup->id));

	$members[$id] = Login::$member;

}
