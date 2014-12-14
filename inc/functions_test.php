<?
/**
 * functions for tests
 *
 * @author Magnus Rosenbaum <dev@cmr.cx>
 * @package Basisentscheid
 */


$password = crypt("test");

$lorem_ipsum = "Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui.

Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia.

Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi.";


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
	$sql = "SELECT * FROM member
 		JOIN member_ngroup ON member.id = member_ngroup.member
 		JOIN vote_token ON vote_token.member = member.id AND vote_token.issue = ".intval($issue->id)."
		WHERE member_ngroup.ngroup = ".$issue->area()->ngroup." AND member.entitled = TRUE
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


/**
 * set the username
 *
 * @param Member  $member
 * @param string  $username
 */
function set_unique_username(Member $member, $username) {
	$member->username = $username;
	$suffix = 0;
	do {
		$sql = "SELECT count(1) FROM member WHERE username=".DB::esc($member->username);
		if ( $exists = DB::fetchfield($sql) ) {
			$member->username = $username . ++$suffix;
		}
	} while ($exists);
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
	DB::insert("member_ngroup", array('member'=>Login::$member->id, 'ngroup'=>$ngroup->id));

	$members[$id] = Login::$member;

}


/**
 * create ngroup
 *
 * @param string  $name
 * @param integer $minimum_population
 * @return Ngroup
 */
function new_ngroup($name, $minimum_population) {
	$ngroup = new Ngroup;
	$ngroup->id = DB::fetchfield("SELECT max(id) FROM ngroup") + 1;
	$ngroup->name = $name." ".$ngroup->id;
	$ngroup->active = true;
	$ngroup->minimum_population = $minimum_population;
	$ngroup->create(['id', 'name', 'active', 'minimum_population']);
	return $ngroup;
}
