<?php

// rotmappe
$root = "/crewstuff/f/";

$redirurl = $_SERVER['REQUEST_URI'];                                                                           
if (($pos = mb_strpos($redirurl, "?")) !== false) $redirurl = mb_substr($redirurl, 0, $pos); 

// finn riktig adresse
$url = isset($redirurl) ? $redirurl : '';
if (mb_substr($url, 0, mb_strlen($root)) === $root)
{
	$url = mb_substr($url, mb_strlen($root));
}

// de ulike delene av adressen
$pages = explode("/", $url);
$page = $pages[0];
$subpage = isset($pages[2]) ? $pages[2] : NULL;

// hente raw? (hent kun essentials)
if (isset($pages[2]) && $pages[2] == "raw" && !isset($pages[3]) && ($page == "rev" || $page == "fil"))
{
	require "../base/essentials.php";
	crewfiles::init();
	global $_base;
	
	$taginfo = crewfiles::get_info($pages[1]);
	if (!$taginfo)
	{
		header("HTTP/1.0 404 Not Found");
		die();
	}
	
	// vise raw for fil?
	if ($page == "fil")
	{
		// hent data, kontroller taginfo og tilgangsnivå
		$file = crewfiles::get_file($taginfo[0]);
		if (!$file || !$file->validate_tag($taginfo[1]) || !$file->access(true))
		{
			header("HTTP/1.0 404 Not Found");
			die;
		}
		
		// finn aktiv revisjon
		$revision = crewfiles::get_revision($file->info['cff_cfr_id']);
		if (!$revision)
		{
			header("HTTP/1.0 404 Not Found");
			die;
		}
	}
	
	// direkte på en revisjon
	else
	{
		// hent data, kontroller taginfo og tilgangsnivå
		$revision = crewfiles::get_revision($taginfo[0]);
		if (!$revision || !$revision->validate_tag($taginfo[1]) || !$revision->access(true))
		{
			header("HTTP/1.0 404 Not Found");
			die;
		}
	}
	
	// hent data
	$data = $revision->get_raw();
	
	// sett headers
	header("Content-Type: {$revision->info['cfr_mime']}");
	header("Content-Disposition: inline; filename=\"{$revision->info['cfr_title']}\"");
	header("Content-Length: {$revision->info['cfr_size']}");
	
	// send data
	echo $data;
	die;
}

// ellers hent vanlig base
else
{
	require "../base/base.php";
	$_base->page->theme_file = "doc";
}

global $__server, $_base;
$_base->page->add_title("Filer");

// sett opp adresse
$rooturl = $__server['relative_path'].$root;
$pageurl = $rooturl.$page;
redirect::store($pageurl, redirect::SERVER);

/*
 * Adresser:
 * tomt: redirect: mappe/0-root
 * 
 * mappe: redirect mappe/0-root
 * mappe/<id>-<navn>
 * mappe/<id>-<navn>/new
 * mappe/<id>-<navn>/delete
 * mappe/<id>-<navn>/edit
 * mappe/<id>-<navn>/upload
 * 
 * fil/<id>-<navn>
 * fil/<id>-<navn>/raw
 * fil/<id>-<navn>/upload
 * fil/<id>-<navn>/edit
 * fil/<id>-<navn>/delete
 * 
 * rev/<id>-<navn>/raw
 * rev/<id>-<navn>/edit
 * rev/<id>-<navn> (POST: Sette aktiv revisjon)
 * rev/<id>-<navn>/delete
 */

// ingenting valgt?
if ($page == "" || ($page == "mappe" && (!isset($pages[1]) || $pages[1] == "")))
{
	// send til rotmappe
	redir_root();
}

// last inn crewfiles systemet
crewfiles::init();

$_base->page->add_css('
.path_all a { margin: -2px; padding: 2px }
.path_active a { background-color: #F3F3F3; font-weight: bold; text-decoration: none }
.path_all a:hover { background-color: #333; color: #FFF }
.revision_active { font-weight: bold }
.revision_active_info { font-weight: normal; color: #AAA }
.show_revision { white-space: nowrap }
.no_desc { color: #DDD }
.rev_links, .file_title { white-space: nowrap }
.rev_mime { color: #AAA; font-weight: normal }
.file_access_level { color: #AAA }
.file_hidden { color: #FF0000 }');

// vise oversikt over alle filene?
if ($page == "map")
{
	// har vi en undermappe?
	if (isset($pages[1]))
	{
		// redirect til riktig adresse
		redirect::handle($rooturl.'map', redirect::SERVER);
	}
	
	$_base->page->add_title("Oversikt over alle filene");
	
	// hent alle filene som tree
	$tree = crewfiles::get_all_files();
	
	echo '
<h1>Filoversikt</h1>
<p class="h_right"><a href="'.$rooturl.'" class="button">Tilbake</a></p>
<p>Denne listen viser en oversikt over alle filene som ligger i dette systemet.</p>';
	
	$hierarchy = array();
	$number = 0;
	foreach ($tree->data as $key => $row)
	{
		// fiks hierarchy
		if ($row['number'] >= $number)
		{
			array_splice($hierarchy, $row['number']);
		}
		
		$hierarchy[] = '<a href="'.$rooturl.'mappe/'.$row['data']['cfd_id'].'-'.htmlspecialchars(crewfiles::generate_tagname($row['data']['cfd_title'])).'">'.htmlspecialchars($row['data']['cfd_title']).'</a>';
		
		// antall filer
		$count = isset($row['cff']) ? count($row['cff']) : 0;
		
		// hoppe over?
		if ($count == 0)
		{
			continue;
		}
		
		echo '
<h2>Mappe: '.implode(" / ", $hierarchy).'</h2>';
		
		// ingen filer?
		if ($count == 0)
		{
			echo '
<p>Denne mappen inneholder ingen filer.</p>';
		}
		
		// vis filene
		else
		{
			echo '
<table class="table tablemb" width="100%">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Beskrivelse</th>
			<th>&nbsp;</th>
			<th width="80">Revisjoner</th>
			<th width="165">Aktiv revisjon</th>
			<th width="60">Størrelse</th>
		</tr>
	</thead>
	<tbody>';
			
			$i = 0;
			foreach ($row['cff'] as $file)
			{
				$tagname = urlencode(crewfiles::generate_tagname($file['cff_title']));
				
				$description = trim(game::format_data($file['cff_description']));
				if (empty($description))
				{
					$description = '<span class="no_desc">Ingen beskrivelse.</span>';
				}
				
				// krever tilgangsnivå?
				$access_info = '';
				if (!empty($file['cff_access_level']))
				{
					$access_info = ' <span class="file_access_level">('.crewfiles::access_name($file['cff_access_level']).')</span>';
				}
				
				// finn filetternavn
				$ext = '';
				if (($pos = mb_strrpos($file['cfr_title'], ".")) !== false)
				{
					$ext = mb_substr($file['cfr_title'], $pos+1);
				}
				
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td class="file_title"><a href="'.$rooturl.'fil/'.$file['cff_id'].'-'.$tagname.'">'.htmlspecialchars($file['cff_title']).'</a>'.($ext != '' ? ' (.'.$ext.')' : '').$access_info.($file['cff_hidden'] != 0 ? ' <span class="file_hidden">(Skjult)</span>' : '').'</td>
			<td>'.$description.'</td>
			<td class="c">'.($file['cfr_id'] ? '<a href="'.$rooturl.'fil/'.$file['cff_id'].'-'.$tagname.'/raw" class="show_revision">Hent aktiv revisjon</a>' : '&nbsp;').'</td>
			<td class="r"><a href="'.$rooturl.'fil/'.$file['cff_id'].'-'.$tagname.'/upload" class="button">Ny revisjon</a> '.$file['count_revisions'].'</td>'.($file['cfr_id'] ? '
			<td class="r">'.$_base->date->get($file['cfr_time'])->format(date::FORMAT_SEC).'</td>
			<td class="r">'.format_size($file['cfr_size']).'</td>' : '
			<td colspan="3">Ingen aktiv revisjon</td>').'
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
		}
	}
	
	$_base->page->load();
}

// mappe?
if ($page == "mappe")
{
	// kontroller mappe
	if (!isset($pages[1]))
	{
		$_base->page->add_message("Fant ikke mappen.", "error");
		redir_root();
	}
	
	// hent taginfo
	$taginfo = crewfiles::get_info($pages[1]);
	if (!$taginfo)
	{
		$_base->page->add_message("Fant ikke mappen.", "error");
		redir_root();
	}
	
	// hent data og kontroller taginfo
	$dir = crewfiles::get_directory($taginfo[0]);
	if (!$dir || !$dir->validate_tag($taginfo[1]))
	{
		$_base->page->add_message("Fant ikke mappen.", "error");
		redir_root();
	}
	
	$mappeurl = $rooturl.'mappe/'.$dir->id.'-'.urlencode(crewfiles::generate_tagname($dir->info['cfd_title']));
	redirect::store($mappeurl, redirect::SERVER);
	
	// sett opp hierarki
	$path = $dir->get_path($rooturl);
	$last = '<span class="path_active">'.array_shift($path).'</span>';
	array_unshift($path, $last);
	$hierarchy = implode(" / ", array_reverse($path));
	
	$_base->page->add_title("Mappe: ".$dir->info['cfd_title']);
	
	// handling: ny undermappe
	if ($subpage == "new")
	{
		// ikke logget inn?
		if (!login::$logged_in)
		{
			$_base->page->add_message("Du må være logget inn for å kunne opprette en mappe.");
			redirect::handle();
		}
		
		// opprette mappe?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$description = trim(postval("description"));
			$access_level = trim(postval("access_level"));
			
			// ingen tilgangsnivå?
			if (empty($access_level))
			{
				$access_level = NULL;
			}
			
			// kontroller tittel
			if (mb_strlen($title) < 3)
			{
				$_base->page->add_message("Tittelen kan ikke være mindre enn 3 tegn.", "error");
			}
			
			// kontroller tilgangsnivå
			elseif ($access_level !== NULL && !crewfiles::validate_access_level($access_level))
			{
				$_base->page->add_message("Ugyldig tilgangsnivå.", "error");
			}
			
			// legg til mappen
			else
			{
				$new = $dir->create_directory($title, $description, $access_level);
				
				$_base->page->add_message("Mappen ble opprettet.");
				redirect::handle($rooturl.'mappe/'.$new->id.'-'.urlencode(crewfiles::generate_tagname($new->info['cfd_title'])), redirect::SERVER);
			}
		}
		
		$_base->page->add_title("Ny mappe");
		
		// vis skjema
		echo '
<h1 class="path_all">Ny mappe: '.$hierarchy.'</h1>
<fieldset>
	<legend>Mappeinformasjon</legend>
	<form action="" method="post">
		<dl class="dl_100px dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" id="dir_title" value="'.htmlspecialchars(postval("title")).'" class="styled w300" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="description" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description")).'</textarea></dd>
			<dt>Tilgangsnivå</dt>
			<dd>
				<select name="access_level">
					<option value="">Alle</option>';
		
		$access_levels = crewfiles::get_access_levels();
		$access_level = postval("access_level");
		foreach ($access_levels as $row)
		{
			echo '
					<option value="'.htmlspecialchars($row).'"'.($row == $access_level ? ' selected="selected"' : '').'>'.crewfiles::access_name($row).'</option>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p>'.show_sbutton("Opprett mappe").' <a href="'.$mappeurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->add_js_domready('function(){$("dir_title").focus();');
		$_base->page->load();
	}
	
	// handling: slett mappe
	if ($subpage == "delete")
	{
		// rotmappe?
		if ($dir->id == 0)
		{
			$_base->page->add_message("Rotmappen kan ikke slettes.", "error");
			redirect::handle();
		}
		
		// sjekk at mappen er tom
		if (!$dir->delete())
		{
			$_base->page->add_message("Mappen må være tom før den kan slettes.", "error");
			redirect::handle();
		}
		
		// godkjent sletting?
		if (isset($_POST['confirm']))
		{
			// slett mappen
			$dir->delete(true);
			
			// infomelding
			$_base->page->add_message("Mappen ble slettet.");
			
			// redirect til parent mappe
			$parent = crewfiles::get_directory($dir->info['cfd_parent_cfd_id']);
			if ($parent)
			{
				redirect::handle($rooturl.'mappe/'.$parent->id.'-'.urlencode(crewfiles::generate_tagname($parent->info['cfd_title'])), redirect::SERVER);
			}
			
			redir_root();
		}
		
		$_base->page->add_title("Slett");
		
		// vis skjema
		echo '
<h1 class="path_all">Slette mappe: '.$hierarchy.'</h1>
<p>Er du sikker på at du ønsker å slette denne mappen?</p>
<form action="" method="post">
	<p>'.show_sbutton("Ja, slett mappen", 'name="confirm"').' <a href="'.$mappeurl.'" class="button">Nei, avbryt</a></p>
</form>';
		
		$_base->page->load();
	}
	
	// handling: rediger mappeinformasjon
	if ($subpage == "edit")
	{
		// ikke høyt nok tilgangsnivå?
		if (!$dir->access)
		{
			$_base->page->add_message('Du har ikke tilgang til å redigere denne mappen. Denne mappen er begrenset til <b>'.crewfiles::access_name($dir->info['cfd_access_level']).'</b>.', "error");
			redirect::handle();
		}
		
		// rotmappe?
		if ($dir->id == 0)
		{
			$_base->page->add_message("Rotmappen kan ikke redigeres.", "error");
			redirect::handle();
		}
		
		// lagre informasjon?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$description = trim(postval("description"));
			$access_level = trim(postval("access_level"));
			
			// ingen tilgangsnivå?
			if (empty($access_level))
			{
				$access_level = NULL;
			}
			
			// kontroller tittel
			if (mb_strlen($title) < 3)
			{
				$_base->page->add_message("Tittelen kan ikke være mindre enn 3 tegn.", "error");
			}
			
			// kontroller tilgangsnivå
			elseif ($access_level !== NULL && !crewfiles::validate_access_level($access_level))
			{
				$_base->page->add_message("Ugyldig tilgangsnivå.", "error");
			}
			
			// ingen endringer?
			elseif ($title == $dir->info['cfd_title'] && $description == $dir->info['cfd_description'] && $access_level == $dir->info['cfd_access_level'])
			{
				$_base->page->add_message("Ingen endringer ble utført.");
				redirect::handle();
			}
			
			// oppdater informasjon
			else
			{
				$dir->edit($title, $description, $access_level);
				
				$_base->page->add_message("Mappen ble oppdatert med endringene.");
				redirect::handle($rooturl.'mappe/'.$dir->id.'-'.urlencode(crewfiles::generate_tagname($dir->info['cfd_title'])), redirect::SERVER);
			}
		}
		
		$_base->page->add_title("Rediger");
		
		// vis skjema
		echo '
<h1 class="path_all">Rediger mappe: '.$hierarchy.'</h1>
<fieldset>
	<legend>Mappeinformasjon</legend>
	<form action="" method="post">
		<dl class="dl_100px dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" id="dir_title" value="'.htmlspecialchars(postval("title", $dir->info['cfd_title'])).'" class="styled w300" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="description" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description", $dir->info['cfd_description'])).'</textarea></dd>
			<dt>Tilgangsnivå</dt>
			<dd>
				<select name="access_level">
					<option value="">Alle</option>';
		
		$access_levels = crewfiles::get_access_levels();
		$access_level = postval("access_level", $dir->info['cfd_access_level']);
		foreach ($access_levels as $row)
		{
			echo '
					<option value="'.htmlspecialchars($row).'"'.($row == $access_level ? ' selected="selected"' : '').'>'.crewfiles::access_name($row).'</option>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$mappeurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->add_js_domready('$("dir_title").focus();');
		$_base->page->load();
	}
	
	// handling: flytt mappe
	if ($subpage == "move")
	{
		// rotmappe?
		if ($dir->id == 0)
		{
			$_base->page->add_message("Rotmappen kan ikke flyttes.", "error");
			redirect::handle();
		}
		
		// flytte mappen?
		if (isset($_POST['cfd_id']))
		{
			// forsøk å flytt mappen
			$status = $dir->move(intval($_POST['cfd_id']));
			
			if ($status !== true)
			{
				switch ($status)
				{
					case "no_change":
						$_base->page->add_message("Ingen endringer ble utført.");
					break;
					
					case "inherit":
						$_base->page->add_message("Mappen kan ikke plasseres i en mappe inni seg selv.", "error");
					break;
					
					case "404":
						$_base->page->add_message("Fant ikke målmappen.", "error");
					break;
					
					default:
						$_base->page->add_message("Ukjent feil.", "error");
				}
			}
			
			else
			{
				// mappen ble flyttet
				$_base->page->add_message("Mappen ble flyttet til <b>".htmlspecialchars($dir->parent_dir->info['cfd_title'])."</b>.");
				
				// redirect
				redirect::handle();
			}
		}
		
		$_base->page->add_title("Flytt mappe");
		
		// hent tree
		$tree = crewfiles::get_directory_tree();
		
		// vis skjema
		echo '
<h1 class="path_all">Flytt mappe: '.$hierarchy.'</h1>
<fieldset>
	<legend>Ny plassering</legend>
	<form action="" method="post">
		<dl class="dl_150px dl_2x">
			<dt>Nåværende plassering</dt>
			<dd>'.$hierarchy.'</dd>
			<dt>Ny plassering</dt>
			<dd>
				<select name="cfd_id" class="plain">';
		
		$valid = true;
		foreach ($tree->data as $row)
		{
			// aktiv mappe?
			if ($row['data']['cfd_id'] == $dir->info['cfd_id'])
			{
				$valid = $row['number'];
			}
			
			// utenfor aktiv mappe
			elseif ($valid !== true && $valid >= $row['number'])
			{
				$valid = true;
			}
			
			echo '
					<option value="'.$row['data']['cfd_id'].'"'.($valid !== true ? ' disabled="disabled"' : ($row['data']['cfd_id'] == $dir->info['cfd_parent_cfd_id'] ? ' selected="selected"' : '')).'>'.$row['prefix'].$row['prefix_node'].' '.htmlspecialchars($row['data']['cfd_title']).'</option>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$mappeurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->load();
	}
	
	// handling: last opp ny fil
	if ($subpage == "upload")
	{
		// ikke logget inn?
		if (!login::$logged_in)
		{
			$_base->page->add_message("Du må være logget inn for å kunne laste opp filer.", "error");
			redirect::handle();
		}
		
		// laste opp?
		if (isset($_FILES['file']))
		{
			/*
			array(1) {
			  ["gi_0"]=>
			  array(5) {
			    ["name"]=>
			    string(13) "SmokeyPoT.jpg"
			    ["type"]=>
			    string(10) "image/jpeg"
			    ["tmp_name"]=>
			    string(14) "/tmp/phpXNDn0i"
			    ["error"]=>
			    int(0)
			    ["size"]=>
			    int(53042)
			  }
			}
			*/
			
			// kontroller filen
			$src = $_FILES['file']['tmp_name'];
			$name = $_FILES['file']['name'];
			$mime = $_FILES['file']['type'];
			
			// sjekk at filen ble lastet opp riktig
			if (!is_uploaded_file($src))
			{
				$_base->page->add_message("Noe gikk galt under opplasting av filen. Prøv på nytt.");
			}
			
			else
			{
				$title = trim(postval("title"));
				$description_file = trim(postval("description_file"));
				$description_rev = trim(postval("description_rev"));
				$access_level = trim(postval("access_level"));
				
				// ingen tilgangsnivå?
				if (empty($access_level))
				{
					$access_level = NULL;
				}
				
				// kontroller tilgangsnivå
				elseif (!crewfiles::validate_access_level($access_level))
				{
					$_base->page->add_message("Ugyldig tilgangsnivå. Ingen tilgangsnivå ble satt.");
					$access_level = NULL;
				}
				
				// ikke spesifisert tittel?
				if (empty($title))
				{
					// sett opp tittel basert på filnavnet uten etternavn og forkort om nødvendig
					$title = $name;
					if (($pos = mb_strrpos($title, ".")) !== false)
					{
						$title = mb_substr($title, 0, $pos);
					}
					if (mb_strlen($title) > 100) $title = mb_substr($title, 0, 100);
				}
				
				// forkort filnavn om nødvendig
				if (mb_strlen($name) > 100)
				{
					$pos = mb_strrpos($title, ".");
					if ($pos !== false)
					{
						$extlen = mb_strlen($name) - $pos + 1;
						$name = mb_substr($name, 0, 100-$extlen) . "." . mb_substr($name, $pos+1);
					}
					else
					{
						$name = mb_substr($name, 0, 100);
					}
				}
				
				// legg til
				$revision = $dir->upload($title, $description_file, $description_rev, $access_level, $name, $mime, $src);
				$path = $rooturl.'fil/'.$revision->get_file()->id.'-'.urlencode(crewfiles::generate_tagname($revision->get_file()->info['cff_title']));
				
				// melding på crewchan
				putlog("CREWCHAN", "%u".login::$user->player->data['up_name']."%u lastet opp %u{$revision->get_file()->info['cff_title']}%u: {$__server['absolute_path']}$path");
				
				// melding og redirect
				$_base->page->add_message("Filen ble lagt til.");
				redirect::handle($path, redirect::SERVER);
			}
		}
		
		$_base->page->add_title("Last opp fil");
		
		// vis skjema
		echo '
<h1 class="path_all">Last opp fil: '.$hierarchy.'</h1>
<fieldset>
	<legend>Fil</legend>
	<form action="" method="post" enctype="multipart/form-data">
		<dl class="dl_100px dl_2x">
			<dt>Fil</dt>
			<dd><input type="file" name="file" class="styled" style="width: 98%" size="70" /></dd>
			<dt>Tittel (<abbr title="Bruker filnavnet uten extension som tittel hvis ikke spesifikert.">valgfri</abbr>)</dt>
			<dd><input type="text" name="title" class="styled w300" /></dd>
			<dt>Beskrivelse for <abbr title="Blir vist i filoversikten">filen</abbr></dt>
			<dd><textarea name="description_file" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description_file")).'</textarea></dd>
			<dt>Beskrivelse for <abbr title="Blir kun vist når man går inn i filen og ser på listen over revisjoner">revisjonen</abbr></dt>
			<dd><textarea name="description_rev" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description_rev")).'</textarea></dd>
			<dt>Tilgangsnivå</dt>
			<dd>
				<select name="access_level">
					<option value="">Alle</option>';
		
		$access_levels = crewfiles::get_access_levels();
		$access_level = postval("access_level");
		foreach ($access_levels as $row)
		{
			echo '
					<option value="'.htmlspecialchars($row).'"'.($row == $access_level ? ' selected="selected"' : '').'>'.crewfiles::access_name($row).'</option>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$mappeurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->load();
	}
	
	// hent undermappene
	$dirs = $dir->get_dirs();
	$count_dirs = count($dirs);
	
	// hent filene
	if ($dir->access)
	{
		// vise skjulte filer?
		$files_show_hidden = isset($_GET['show_hidden_files']);
		
		// hent filene
		$files = $dir->get_files();
		
		// sjekk om noen av filene er skjult
		$count_files_hidden = 0;
		foreach ($files as $file)
		{
			if ($file['cff_hidden'] != 0)
			{
				$count_files_hidden++;
			}
		}
		
		// antall filer som skal vises
		$count_files = count($files) - ($files_show_hidden ? 0 : $count_files_hidden);
	}
	
	// vis/skjul skjulte filer
	$hidden_files_link = !$dir->access || $count_files_hidden == 0 ? '' : ($files_show_hidden ? '
	<a href="'.$mappeurl.'" class="button">Ikke vis skjulte filer ('.$count_files_hidden.')</a>' : '
	<a href="'.$mappeurl.'?show_hidden_files" class="button">Vis skjulte filer ('.$count_files_hidden.')</a>');
	
	// vis mappeinformasjon og innhold
	echo '
<h1 class="path_all">Mappe: '.$hierarchy.'</h1>
<p class="h_right">'.$hidden_files_link.'
	<a href="'.$mappeurl.'/move" class="button">Flytt mappe</a>
	<a href="'.$mappeurl.'/edit" class="button">Rediger informasjon</a>
	<a href="'.$mappeurl.'/new" class="button">Ny mappe her</a>
	<a href="'.$mappeurl.'/delete" class="button">Slett mappe</a>
	<a href="'.$mappeurl.'/upload" class="button">Last opp fil</a>
</p>';
	
	// vis hurtiglinker til alle mappene
	$tree = crewfiles::get_directory_tree();
	echo '
<p>
	Hurtignavigasjon: <select class="plain" onchange="navigateTo(this.value)">';
	
	foreach ($tree->data as $row)
	{
		echo '
		<option value="'.$rooturl.'mappe/'.$row['data']['cfd_id'].'-'.urlencode(crewfiles::generate_tagname($row['data']['cfd_title'])).'"'.($row['data']['cfd_id'] == $dir->info['cfd_id'] ? ' selected="selected"' : '').'>'.$row['prefix'].$row['prefix_node'].' '.htmlspecialchars($row['data']['cfd_title']).'</option>';
	}
	
	echo '
	</select>
</p>';
	
	// vis beskrivelse
	$description = trim(game::format_data($dir->info['cfd_description']));
	if (!empty($description))
	{
		echo '
<div class="p">'.$description.'</div>';
	}
	
	// vis beskrivelse for rotmappen
	if ($dir->info['cfd_id'] == 0)
	{
		echo '
<p>Velkommen til filsystemet til Crewet! Dette systemet er ment for å være et knytepunkt for filer som er relevant for Crewet. Har du skrevet en idé eller liknende er det bare å laste opp!</p>
<p><a href="'.$rooturl.'map">Trykk her for oversikt over alle filene i systemet &raquo;</a></p>';
	}
	
	// vis undermapper
	if ($count_dirs > 0)
	{
		echo '
<fieldset>
	<legend>Undermapper</legend>
	<table class="table tablem" width="100%">
		<thead>
			<tr>
				<th>Tittel</th>
				<th>Beskrivelse</th>
				<th width="80">Undermapper</th>
				<th width="80">Filer</th>
				<th width="100">Filtilgang</th>
			</tr>
		</thead>
		<tbody>';
		
		$i = 0;
		foreach ($dirs as $row)
		{
			// krever tilgangsnivå?
			$access_info = '<span class="no_desc">Ikke satt</span>';
			if (!empty($row['cfd_access_level']))
			{
				$access_info = crewfiles::access_name($row['cfd_access_level']);
			}
			
			// beskrivelse
			$description = trim(game::format_data($row['cfd_description']));
			if (empty($description))
			{
				$description = '<span class="no_desc">Ingen beskrivelse.</span>';
			}
			
			echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td><a href="'.$rooturl.'mappe/'.$row['cfd_id'].'-'.urlencode(crewfiles::generate_tagname($row['cfd_title'])).'">'.htmlspecialchars($row['cfd_title']).'</a></td>
				<td>'.$description.'</td>
				<td class="r">'.$row['count_dirs'].'</td>
				<td class="r">'.$row['count_files'].'</td>
				<td>'.$access_info.'</td>
			</tr>';
		}
		
		echo '
		</tbody>
	</table>
</fieldset>';
	}
	
	// tilgang til filer?
	if ($dir->access)
	{
		echo '
<fieldset>
	<legend>Filer</legend>';
		
		// krever tilgangsnivå?
		if (!empty($dir->info['cfd_access_level']))
		{
			echo '
	<p style="margin-bottom: 0">Filene i denne mappen er kun synlig for brukere med tilgangsnivå <b>'.crewfiles::access_name($dir->info['cfd_access_level']).'</b>.</p>';
		}
		
		// er det noen filer å vise?
		if ($count_files > 0)
		{
			echo '
	<table class="table tablem" width="100%">
		<thead>
			<tr>
				<th>Tittel</th>
				<th>Beskrivelse</th>
				<th>&nbsp;</th>
				<th width="80">Revisjoner</th>
				<th width="165">Aktiv revisjon</th>
				<th width="60">Størrelse</th>
			</tr>
		</thead>
		<tbody>';
			
			$i = 0;
			foreach ($files as $row)
			{
				// skjult fil? (og skal ikke vise skjulte filer)
				if ($row['cff_hidden'] != 0 && !$files_show_hidden)
				{
					// hopp over filen
					continue;
				}
				
				$tagname = urlencode(crewfiles::generate_tagname($row['cff_title']));
				
				$description = trim(game::format_data($row['cff_description']));
				if (empty($description))
				{
					$description = '<span class="no_desc">Ingen beskrivelse.</span>';
				}
				
				// krever tilgangsnivå?
				$access_info = '';
				if (!empty($row['cff_access_level']))
				{
					$access_info = ' <span class="file_access_level">('.crewfiles::access_name($row['cff_access_level']).')</span>';
				}
				
				// finn filetternavn
				$ext = '';
				if (($pos = mb_strrpos($row['cfr_title'], ".")) !== false)
				{
					$ext = mb_substr($row['cfr_title'], $pos+1);
				}
				
				echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td class="file_title"><a href="'.$rooturl.'fil/'.$row['cff_id'].'-'.$tagname.'">'.htmlspecialchars($row['cff_title']).'</a>'.($ext != '' ? ' (.'.$ext.')' : '').$access_info.($row['cff_hidden'] != 0 ? ' <span class="file_hidden">(Skjult)</span>' : '').'</td>
				<td>'.$description.'</td>
				<td class="c">'.($row['cfr_id'] ? '<a href="'.$rooturl.'fil/'.$row['cff_id'].'-'.$tagname.'/raw" class="show_revision">Hent aktiv revisjon</a>' : '&nbsp;').'</td>
				<td class="r"><a href="'.$rooturl.'fil/'.$row['cff_id'].'-'.$tagname.'/upload" class="button">Ny revisjon</a> '.$row['count_revisions'].'</td>'.($row['cfr_id'] ? '
				<td class="r">'.$_base->date->get($row['cfr_time'])->format(date::FORMAT_SEC).'</td>
				<td class="r">'.format_size($row['cfr_size']).'</td>' : '
				<td colspan="3">Ingen aktiv revisjon</td>').'
			</tr>';
			}
			
			echo '
		</tbody>
	</table>';
		}
		
		// ingen filer
		else
		{
			echo '
	<p>Det er ingen filer i denne mappen.</p>';
		}
		
		echo '
</fieldset>';
	}
	
	// ikke tilgang til filene i mappen
	else
	{
		echo '
<fieldset>
	<legend>Filer</legend>
	<p>Du har ikke tilgang til filene i denne mappen.<br />Krever tilgangen <b>'.crewfiles::access_name($dir->info['cfd_access_level']).'</b>.</p>
</fieldset>';
	}
	
	$_base->page->load();
}

// fil?
elseif ($page == "fil")
{
	// kontroller fil
	if (!isset($pages[1]))
	{
		$_base->page->add_message("Fant ikke filen.", "error");
		redir_root();
	}
	
	// hent taginfo
	$taginfo = crewfiles::get_info($pages[1]);
	if (!$taginfo)
	{
		$_base->page->add_message("Fant ikke filen.", "error");
		redir_root();
	}
	
	// hent data, kontroller taginfo og tilgangsnivå
	$file = crewfiles::get_file($taginfo[0]);
	if (!$file || !$file->validate_tag($taginfo[1]) || !$file->access)
	{
		$_base->page->add_message("Fant ikke filen.", "error");
		redir_root();
	}
	
	$filurl = $rooturl.'fil/'.$file->id.'-'.urlencode(crewfiles::generate_tagname($file->info['cff_title']));
	redirect::store($filurl, redirect::SERVER);
	
	// sett opp hierarki
	$path = $file->get_dir()->get_path($rooturl);
	$last = '<span class="path_active"><a href="'.$filurl.'">'.htmlspecialchars($file->info['cff_title']).'</a></span>';
	array_unshift($path, $last);
	$hierarchy = implode(" / ", array_reverse($path));
	
	$_base->page->add_title("Fil: ".$file->info['cff_title']);
	
	// handling: slett fil
	if ($subpage == "delete")
	{
		// sjekk at filen ikke inneholder noen revisjoner
		if (!$file->delete())
		{
			$_base->page->add_message("Filen kan ikke inneholde noen revisjoner for å bli slettet.", "error");
			redirect::handle();
		}
		
		// godkjent sletting?
		if (isset($_POST['confirm']))
		{
			// slett filen
			$file->delete(true);
			
			// infomelding
			$_base->page->add_message("Filen ble slettet.");
			
			// redirect til mappen
			$dir = $file->get_dir();
			redirect::handle($rooturl.'mappe/'.$dir->id.'-'.urlencode(crewfiles::generate_tagname($dir->info['cfd_title'])), redirect::SERVER);
		}
		
		$_base->page->add_title("Slett");
		
		// vis skjema
		echo '
<h1 class="path_all">Slette fil: '.$hierarchy.'</h1>
<p>Er du sikker på at du ønsker å slette denne filen?</p>
<form action="" method="post">
	<p>'.show_sbutton("Ja, slett filen", 'name="confirm"').' <a href="'.$filurl.'" class="button">Nei, avbryt</a></p>
</form>';
		
		$_base->page->load();
	}
	
	// handling: rediger filinformasjon
	if ($subpage == "edit")
	{
		// lagre informasjon?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$description = trim(postval("description"));
			$access_level = trim(postval("access_level"));
			$hidden = isset($_POST['hidden']);
			
			// ingen tilgangsnivå?
			if (empty($access_level))
			{
				$access_level = NULL;
			}
			
			// kontroller tittel
			if (mb_strlen($title) < 3)
			{
				$_base->page->add_message("Tittelen kan ikke være mindre enn 3 tegn.", "error");
			}
			
			// kontroller tilgangsnivå
			elseif ($access_level !== NULL && !crewfiles::validate_access_level($access_level))
			{
				$_base->page->add_message("Ugyldig tilgangsnivå.", "error");
			}
			
			// ingen endringer?
			elseif ($title == $file->info['cff_title'] && $description == $file->info['cff_description'] && $access_level == $file->info['cff_access_level'] && ($hidden ? 1 : 0) == $file->info['cff_hidden'])
			{
				$_base->page->add_message("Ingen endringer ble utført.");
				redirect::handle();
			}
			
			// oppdater informasjon
			else
			{
				$file->edit($title, $description, $access_level, $hidden);
				
				$_base->page->add_message("Filen ble oppdatert med endringene.");
				redirect::handle($rooturl.'fil/'.$file->id.'-'.urlencode(crewfiles::generate_tagname($file->info['cff_title'])), redirect::SERVER);
			}
		}
		
		$_base->page->add_title("Rediger");
		
		// vise/skjule filen
		$hidden = $_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['hidden']) ? '' : ($file->info['cff_hidden'] == 0 ? '' : ' checked="checked"');
		
		// vis skjema
		echo '
<h1 class="path_all">Rediger fil: '.$hierarchy.'</h1>
<fieldset>
	<legend>Filinformasjon</legend>
	<form action="" method="post">
		<dl class="dl_100px dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" id="file_title" value="'.htmlspecialchars(postval("title", $file->info['cff_title'])).'" class="styled w300" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="description" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description", $file->info['cff_description'])).'</textarea></dd>
			<dt>Tilgangsnivå</dt>
			<dd>
				<select name="access_level">
					<option value="">Alle</option>';
		
		$access_levels = crewfiles::get_access_levels();
		$access_level = postval("access_level", $file->info['cff_access_level']);
		foreach ($access_levels as $row)
		{
			echo '
					<option value="'.htmlspecialchars($row).'"'.($row == $access_level ? ' selected="selected"' : '').'>'.crewfiles::access_name($row).'</option>';
		}
		
		echo '
				</select>
			</dd>
			<dt><label for="file_hidden"><abbr title="Skjuler filen fra mappen. Filen kan vises ved å trykke på &laquo;Vis skjulte filer&raquo;.">Skjul filen</abbr></label></dt>
			<dd><input type="checkbox" name="hidden" id="file_hidden"'.$hidden.' /></dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$filurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->add_js_domready('function(){$("file_title").focus();');
		$_base->page->load();
	}
	
	// handling: flytt fil
	if ($subpage == "move")
	{
		// flytte filen?
		if (isset($_POST['cfd_id']))
		{
			// forsøk å flytt filen
			$status = $file->move(intval($_POST['cfd_id']));
			
			if ($status !== true)
			{
				switch ($status)
				{
					case "no_change":
						$_base->page->add_message("Ingen endringer ble utført.");
					break;
					
					case "no_access":
						$_base->page->add_message("Du har ikke filtilgang til mappen du ønsker å flytte filen til.", "error");
					break;
					
					case "404":
						$_base->page->add_message("Fant ikke målmappen.", "error");
					break;
					
					default:
						$_base->page->add_message("Ukjent feil.", "error");
				}
			}
			
			else
			{
				// filen ble flyttet
				$_base->page->add_message("Filen ble flyttet til <b>".htmlspecialchars($file->get_dir()->info['cfd_title'])."</b>.");
				
				// redirect
				redirect::handle();
			}
		}
		
		$_base->page->add_title("Flytt fil");
		
		// hent tree
		$tree = crewfiles::get_directory_tree();
		
		// vis skjema
		echo '
<h1 class="path_all">Flytt fil: '.$hierarchy.'</h1>
<fieldset>
	<legend>Ny plassering</legend>
	<form action="" method="post">
		<dl class="dl_150px dl_2x">
			<dt>Nåværende plassering</dt>
			<dd>'.$hierarchy.'</dd>
			<dt>Ny plassering</dt>
			<dd>
				<select name="cfd_id" class="plain">';
		
		foreach ($tree->data as $row)
		{
			// filtilgang?
			$access = empty($row['data']['cfd_access_level']) || crewfiles::access($row['data']['cfd_access_level']);
			
			echo '
					<option value="'.$row['data']['cfd_id'].'"'.(!$access ? ' disabled="disabled"' : ($row['data']['cfd_id'] == $file->info['cff_cfd_id'] ? ' selected="selected"' : '')).'>'.$row['prefix'].$row['prefix_node'].' '.htmlspecialchars($row['data']['cfd_title']).(!$access ? ' (ikke filtilgang)' : '').'</option>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$filurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->load();
	}
	
	// handling: last opp ny revisjon
	if ($subpage == "upload")
	{
		// ikke logget inn?
		if (!login::$logged_in)
		{
			$_base->page->add_message("Du må være logget inn for å kunne laste opp nye revisjoner.", "error");
			redirect::handle();
		}
		
		// laste opp?
		if (isset($_FILES['file']))
		{
			// kontroller filen
			$src = $_FILES['file']['tmp_name'];
			$name = $_FILES['file']['name'];
			$mime = $_FILES['file']['type'];
			
			// sjekk at filen ble lastet opp riktig
			if (!is_uploaded_file($src))
			{
				$_base->page->add_message("Noe gikk galt under opplasting av filen. Prøv på nytt.");
			}
			
			else
			{
				$description = trim(postval("description"));
				$set_active = isset($_POST['set_active']);
				
				// forkort filnavn om nødvendig
				if (mb_strlen($name) > 100)
				{
					$pos = mb_strrpos($title, ".");
					if ($pos !== false)
					{
						$extlen = mb_strlen($name) - $pos + 1;
						$name = mb_substr($name, 0, 100-$extlen) . "." . mb_substr($name, $pos+1);
					}
					else
					{
						$name = mb_substr($name, 0, 100);
					}
				}
				
				// legg til
				$revision = $file->upload($name, $description, $mime, $src, $set_active);
				$active = $set_active ? ' Revisjonen ble satt som aktiv revisjon.' : '';
				
				// melding på crewchan
				$path = $rooturl.'rev/'.$revision->id.'-'.urlencode(crewfiles::generate_tagname($revision->info['cfr_title']));
				putlog("CREWCHAN", "%u".login::$user->player->data['up_name']."%u lastet opp ny revisjon til %u{$file->info['cff_title']}%u: {$__server['absolute_path']}$path");
				
				// melding og redirect
				$_base->page->add_message("Ny revisjon ble lastet opp.".$active);
				redirect::handle();
			}
		}
		
		$_base->page->add_title("Last opp ny revisjon");
		
		// sette aktiv?
		$set_active = $_SERVER['REQUEST_METHOD'] == "POST" && !isset($_POST['set_active']) ? '' : ' checked="checked"';
		
		// vis skjema
		echo '
<h1 class="path_all">Last opp ny revisjon: '.$hierarchy.'</h1>
<fieldset>
	<legend>Fil</legend>
	<form action="" method="post" enctype="multipart/form-data">
		<dl class="dl_100px dl_2x">
			<dt>Fil</dt>
			<dd><input type="file" name="file" class="styled" style="width: 98%" size="70" /></dd>
			<dt><label for="rev_set_active">Sett som aktiv</label></dt>
			<dd><input type="checkbox" name="set_active" id="rev_set_active"'.$set_active.' /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="description" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description")).'</textarea></dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$filurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->load();
	}
	
	// vis filinformasjon og revisjoner
	$revisions = $file->get_revisions();
	$count_revisions = count($revisions);
	
	echo '
<h1 class="path_all">Fil: '.$hierarchy.'</h1>
<p class="h_right">
	<a href="'.$filurl.'/move" class="button">Flytt fil</a>
	<a href="'.$filurl.'/edit" class="button">Rediger informasjon</a>
	<a href="'.$filurl.'/delete" class="button">Slett fil</a>
	<a href="'.$filurl.'/upload" class="button">Last opp ny revisjon</a>
</p>';
	
	// vis beskrivelse
	$description = trim(game::format_data($file->info['cff_description']));
	if (!empty($description))
	{
		echo '
<div class="p">'.$description.'</div>';
	}
	
	// krever tilgangsnivå?
	if (!empty($file->info['cff_access_level']))
	{
		echo '
	<p>Denne filen er kun synlig for brukere med tilgangsnivå <b>'.crewfiles::access_name($file->info['cff_access_level']).'</b>.</p>';
	}
	
	// skjult?
	if ($file->info['cff_hidden'] != 0)
	{
		echo '
	<p>Denne filen er <b>skjult</b> fra standard mappevisning.</p>';
	}
	
	// vis revisjonene
	echo '
<fieldset>
	<legend>Revisjoner</legend>';
	
	// er det noen revisjoner å vise?
	if ($count_revisions > 0)
	{
		echo '
	<table class="table tablem" width="100%">
		<thead>
			<tr>
				<th>Filnavn</th>
				<th>Beskrivelse</th>
				<th width="165">Lastet opp</th>
				<th>Av</th>
				<th width="60">Størrelse</th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>';
		
		$i = 0;
		foreach ($revisions as $row)
		{
			$tagname = urlencode(crewfiles::generate_tagname($row['cfr_title']));
			$revurl = $rooturl.'rev/'.$row['cfr_id'].'-'.$tagname;
			
			$description = trim(game::format_data($row['cfr_description']));
			if (empty($description))
			{
				$description = '<span class="no_desc">Ingen beskrivelse.</span>';
			}
			
			++$i;
			
			#'.($row['cfr_id'] == $file->info['cff_cfr_id'] ? ' class="highlight"' : ($i % 2 == 0 ? ' class="color"' : '')).'
			
			echo '
			<tr'.($i % 2 == 0 ? ' class="color"' : '').'>
				<td'.($row['cfr_id'] == $file->info['cff_cfr_id'] ? ' class="revision_active"' : '').'><a href="'.$revurl.'/raw">'.htmlspecialchars($row['cfr_title']).'</a>'.($row['cfr_id'] == $file->info['cff_cfr_id'] ? '<span class="revision_active_info"> [aktiv]</span>' : '').'<br /><span class="rev_mime">'.htmlspecialchars($row['cfr_mime']).'</span></td>
				<td>'.$description.'</td>
				<td class="r">'.$_base->date->get($row['cfr_time'])->format(date::FORMAT_SEC).'</td>
				<td>'.game::profile_link($row['cfr_up_id'], $row['up_name'], $row['up_access_level']).'</td>
				<td class="r">'.format_size($row['cfr_size']).'</td>
				<td class="r rev_links">'.($row['cfr_id'] == $file->info['cff_cfr_id'] ? '' : '
					<a href="'.$revurl.'/active" class="button">Sett aktiv</a>').'
					<a href="'.$revurl.'/edit" class="button">Rediger</a>
					<a href="'.$revurl.'/delete" class="button">Slett</a>
				</td>
			</tr>';
		}
		
		echo '
		</tbody>
	</table>';
	}
	
	// ingen revisjoner
	else
	{
		echo '
	<p>Det er ingen revisjoner i denne filen.</p>';
	}
	
	echo '
</fieldset>';
}

// revisjon
elseif ($page == "rev")
{
	// kontroller revisjon
	if (!isset($pages[1]))
	{
		$_base->page->add_message("Fant ikke revisjonen.", "error");
		redir_root();
	}
	
	// hent taginfo
	$taginfo = crewfiles::get_info($pages[1]);
	if (!$taginfo)
	{
		$_base->page->add_message("Fant ikke revisjonen.", "error");
		redir_root();
	}
	
	// hent data, kontroller taginfo og tilgangsnivå
	$revision = crewfiles::get_revision($taginfo[0]);
	if (!$revision || !$revision->validate_tag($taginfo[1]) || !$revision->get_file()->access)
	{
		$_base->page->add_message("Fant ikke revisjonen.", "error");
		redir_root();
	}
	
	$revurl = $rooturl.'rev/'.$revision->id.'-'.urlencode(crewfiles::generate_tagname($revision->info['cfr_title']));
	
	$file = &$revision->get_file();
	$filurl = $rooturl.'fil/'.$file->id.'-'.urlencode(crewfiles::generate_tagname($file->info['cff_title']));
	redirect::store($filurl, redirect::SERVER);
	
	// sett opp hierarki
	$path = $revision->get_file()->get_dir()->get_path($rooturl);
	array_unshift($path, '<a href="'.$filurl.'">'.htmlspecialchars($file->info['cff_title']).'</a>');
	array_unshift($path, '<span class="path_active"><a href="'.$revurl.'">'.htmlspecialchars($revision->info['cfr_title']).'</a></span>');
	$hierarchy = implode(" / ", array_reverse($path));
	
	$_base->page->add_title("Revisjon: ".$revision->info['cfr_title']);
	
	// handling: slett revisjon
	if ($subpage == "delete")
	{
		// ikke logget inn?
		if (!login::$logged_in)
		{
			$_base->page->add_message("Du må være logget inn for å kunne slette revisjoner.", "error");
			redirect::handle();
		}
		
		// har vi ikke tilgang til å slette denne revisjonen?
		if ($revision->info['cfr_up_id'] != login::$user->player->id && !access::has("admin"))
		{
			$_base->page->add_message("Denne revisjonen tilhører ikke deg. Du har derfor ikke tilgang til å slette revisjonen.", "error");
			redirect::handle();
		}
		
		// godkjent?
		if (isset($_POST['confirm']))
		{
			// slett revisjonen
			$revision->delete();
			
			$_base->page->add_message("Revisjonen ble slettet.");
			redirect::handle();
		}
		
		// ikke godkjent?
		if (isset($_POST['do']))
		{
			$_base->page->add_message("Revisjonen ble ikke slettet.");
			redirect::handle();
		}
		
		$_base->page->add_title("Slett");
		
		// vis skjema
		echo '
<h1 class="path_all">Slette revisjon: '.$hierarchy.'</h1>
<p>Er du sikker på at du ønsker å slette denne revisjonen?</p>
<div class="warning">
	<p>Revisjonen vil bli slettet permanent.</p>
</div>
<form action="" method="post">
	<p><input type="checkbox" name="confirm" id="confirm_del" /><label for="confirm_del"> Bekreft</label></p>
	<p>'.show_sbutton("Slett", 'name="do"').' <a href="'.$filurl.'" class="button">Avbryt</a></p>
</form>
<p>Lastet opp '.$_base->date->get($revision->info['cfr_time'])->format(date::FORMAT_SEC).' av <user id="'.$revision->info['cfr_up_id'].'" />.</p>';
		
		// vis beskrivelse
		$description = trim(game::format_data($revision->info['cfr_description']));
		if (!empty($description))
		{
			echo '
<div class="p">'.$description.'</div>';
		}
		
		echo '
<p><a href="'.$revurl.'/raw"><b>Hent fil &raquo;</b></a></p>';
		
		$_base->page->load();
	}
	
	// handling: sett aktiv revisjon
	if ($subpage == "active")
	{
		// allerede aktiv?
		if ($revision->id == $file->info['cff_cfr_id'])
		{
			$_base->page->add_message("Denne revisjonen er allerede aktiv.");
		}
		
		else
		{
			// sett som aktiv
			$revision->set_active();
			$_base->page->add_message("Revisjonen er nå satt som aktiv revisjon.");
		}
		
		redirect::handle();
	}
	
	// handling: rediger revisjoninformasjon
	if ($subpage == "edit")
	{
		// lagre informasjon?
		if (isset($_POST['title']))
		{
			$title = trim(crewfiles::filter_filename(postval("title")));
			$description = trim(postval("description"));
			$mime = trim(postval("mime"));
			
			// kontroller tittel
			if (mb_strlen($title) < 3)
			{
				$_base->page->add_message("Tittelen kan ikke være mindre enn 3 tegn.", "error");
			}
			
			// ingen endringer?
			elseif ($title == $revision->info['cfr_title'] && $description == $revision->info['cfr_description'] && $mime == $revision->info['cfr_mime'])
			{
				$_base->page->add_message("Ingen endringer ble utført.");
				redirect::handle();
			}
			
			// oppdater informasjon
			else
			{
				$revision->edit($title, $description, $mime);
				
				$_base->page->add_message("Revisjonen ble oppdatert med endringene.");
				redirect::handle();
			}
		}
		
		$_base->page->add_title("Rediger");
		
		// vis skjema
		echo '
<h1 class="path_all">Rediger revisjon: '.$hierarchy.'</h1>
<fieldset>
	<legend>Revisjoninformasjon</legend>
	<form action="" method="post">
		<dl class="dl_100px dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" id="rev_title" value="'.htmlspecialchars(postval("title", $revision->info['cfr_title'])).'" class="styled w300" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="description" rows="10" cols="50" style="width: 98%">'.htmlspecialchars(postval("description", $revision->info['cfr_description'])).'</textarea></dd>
			<dt>Mimetype</dt>
			<dd><input type="text" name="mime" value="'.htmlspecialchars(postval("mime", $revision->info['cfr_mime'])).'" class="styled w300" /> <i>(ekspertmodus)</i></dd>
		</dl>
		<p>'.show_sbutton("Lagre endringer").' <a href="'.$filurl.'">Avbryt</a></p>
	</form>
</fieldset>';
		
		$_base->page->add_js_domready('function(){$("rev_title").focus();');
		$_base->page->load();
	}
	
	// ukjent handling - send til filen
	redirect::handle();
}

$_base->page->load();

function redir_root()
{
	global $rooturl;
	redirect::handle($rooturl."mappe/0-".urlencode(crewfiles::generate_tagname(crewfiles_directory::$root['cfd_title'])), redirect::SERVER);
}

function format_size($bytes)
{
	// GB
	if ($bytes >= 1073741824)
	{
		return game::format_number(round($bytes/1073741824, 3), 3) . " GB";
	}
	
	// MB
	if ($bytes >= 1048576)
	{
		return game::format_number(round($bytes/1048576, 2), 2) . " MB";
	}
	
	// KB
	if ($bytes >= 1024)
	{
		return game::format_number(round($bytes/1024, 2), 2) . " KB";
	}
	
	// bytes
	return $bytes . " bytes";
}
