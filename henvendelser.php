<?php

define("FORCE_HTTPS", true);
define("ALLOW_GUEST", true);
require "base.php";

global $__server, $_base;

$_base->page->add_title("Henvendelser");

$categories = array(
	1 => "Generelt",
	"Utestengt/deaktivert",
	"Feil/bugs",
	"Forslag til funksjon",
	"Annet"
);
$status = array(
	"crew" => array(
		0 => "Ny",
		1 => "Under behandling",
		2 => "Venter på svar",
		3 => "Ferdig behandlet",
		4 => "Slettet"
	),
	"other" => array(
		0 => "Ikke behandlet",
		1 => "Under behandling",
		2 => "Trenger svar",
		3 => "Ferdig behandlet",
		4 => "Slettet"
	)
);

// administrasjon
if (isset($_GET['a']) && access::has("mod", NULL, NULL, true))
{
	redirect::store("henvendelser?a");
	
	// ikke authed?
	if (!access::has("mod"))
	{
		echo '
<h1>Henvendelser</h1>
<p>Du må logge inn for utvidede tilganger for å få tilgang til henvendelsene som er sendt inn.</p>';
		
		$_base->page->load();
	}
	
	// bestemt henvendelse?
	if (isset($_GET['h_id']))
	{
		$h_id = intval($_GET['h_id']);
		$bb = true;
		
		// hent henvendelsen
		$result = $_base->db->query("SELECT h_id, h_name, h_category, h_email, h_subject, h_name, h_status, h_time, h_random, h_last_visit FROM henvendelser WHERE h_id = $h_id");
		$h = mysql_fetch_assoc($result);
		
		if (!$h)
		{
			$_base->page->add_message("Fant ikke henvendelsen.", "error");
			redirect::handle();
		}
		
		// opprette svar?
		$preview = false;
		if (isset($_POST['reply']) && (isset($_POST['preview']) || isset($_POST['add'])))
		{
			$content = trim(postval("content"));
			$n_status = intval(postval("status", -2));
			$crewonly = isset($_POST['crewonly']);
			
			// status ikke endret?
			if ($n_status == -1) $n_status = $h['h_status'];
			
			// ugyldig status?
			if ($n_status < 2 || $n_status > 4)
			{
				$_base->page->add_message("Ugyldig status.", "error");
			}
			
			// ingen endringer
			elseif ($content == "" && $n_status == $h['h_status'])
			{
				$_base->page->add_message("Ingen endringer ble utført.", "error");
			}
			
			// status til slettet og melding?
			elseif ($n_status == 4 && $content != "" && !$crewonly)
			{
				$_base->page->add_message("Kan ikke legge til melding samtidig som status er/blir satt til slettet uten at den kun er synlig for Crewet.", "error");
			}
			
			// forhåndsvise?
			elseif (isset($_POST['preview']))
			{
				$_base->page->add_message("Viser forhåndsvisning.");
				$preview = true;
			}
			
			// utfør endringene
			else
			{
				$set = array();
				$email_content = false;
				$email_status = false;
				
				// legge til melding?
				if ($content != "")
				{
					$hm_time = time();
					$set[] = "h_hm_time = $hm_time";
					if (!$crewonly) $email_content = true;
					$_base->db->query("INSERT INTO henvendelser_messages SET hm_h_id = $h_id, hm_time = $hm_time, hm_ip = ".$_base->db->quote($_SERVER['REMOTE_ADDR']).", hm_up_id = ".login::$user->player->id.", hm_browser = ".$_base->db->quote($_SERVER['HTTP_USER_AGENT']).", hm_content = ".$_base->db->quote($content).", hm_crew = 1".($crewonly ? ', hm_type = 1' : ''));
				}
				
				// oppdatere status?
				if ($n_status != $h['h_status'])
				{
					$set[] = "h_status = $n_status";
					if (($n_status != 1 || $email_content) && $n_status != 4) $email_status = true;
				}
				
				// oppdater henvendelsen
				$_base->db->query("UPDATE henvendelser SET ".implode(",", $set)." WHERE h_id = $h_id");
				
				// oppdater cache
				tasks::set("henvendelser", mysql_result($_base->db->query("SELECT COUNT(h_id) FROM henvendelser WHERE h_status = 0"), 0));
				
				$_base->page->add_message("Endringene ble utført.".($email_content || $email_status ? ' E-post ble sendt.' : ''));
				
				// sende e-post?
				if ($email_content || $email_status)
				{
					$title = $email_content ? 'Ny melding i henvendelse' : 'Status endret for henvendelse';

					$email = new email();
					$email->text = "$title (".$_base->date->get()->format(date::FORMAT_SEC)."):

Direktelink: {$__server['path']}/henvendelser?id={$h['h_random']}&email={$h['h_email']}

Kategori: {$h['h_category']}
Emne: {$h['h_subject']}";
					
					// status endret?
					if ($email_status)
					{
						$email->text .= "

Gammel status: {$status['other'][$h['h_status']]}
Ny status: {$status['other'][$n_status]}";
					}
					
					// vise melding?
					// TODO: vise melding?
					
					if ($email_status && $n_status == 2)
					{
						$email->text .= "

Denne henvendelsen trenger svar/mer informasjon. Gå inn på henvendelsen ved hjelp av linken ovenfor.";
					}
					
					elseif ($email_content)
					{
						$email->text .= "

For å lese og evt. besvare denne henvendelsen ytterligere må du gå inn på henvendelsen ved hjelp av linken ovenfor.";
					}
					
					$email->text .= "

Denne e-posten kan ikke besvares.";
					
					$email->send($h['h_email'], "$title - {$h['h_category']}: {$h['h_subject']}");
				}
				
				// send til hovedsiden hvis henvendelsen er avsluttet uten noen melding
				if (($n_status == 3 || $n_status == 4) && $content == "")
				{
					redirect::handle("henvendelser?a");
				}
				
				redirect::handle("henvendelser?a&h_id=$h_id");
			}
		}
		
		// hent alle meldingene
		$result = $_base->db->query("SELECT hm_time, hm_ip, hm_up_id, hm_browser, hm_content, hm_type, hm_crew FROM henvendelser_messages WHERE hm_h_id = $h_id ORDER BY hm_time");
		
		$_base->page->add_css('
.henvendelser_melding {
	margin: 10px 0 15px;
}
.hm_crew .hm_title {
	background-color: #004444;
}
.hm_crew .hm_content, .hm_crew .hm_crewonly {
	background-color: #002F2F;
}
.hm_crew .hm_crewonly {
	color: #888888;
}
.hm_title {
	margin: 0;
	background-color: #2D2D2D;
	padding: 3px 5px;
	text-align: right;
	color: #888888;
}
.hm_title .hm_by {
	float: left;
	color: #EEEEEE;
}
.hm_content, .hm_crewonly {
	margin: 0;
	padding: 5px;
	background-color: #1F1F1F;
}
.hm_footer {
	margin: 0;
	padding: 3px 0;
	color: #444444;
	font-size: 10px;
	text-align: right;
}

.h_reply {
	margin: 15px auto;
	width: 505px;
	background-color: #1F1F1F;
	padding: 5px;
}
.h_reply .h_title {
	margin: -5px -5px 0 -5px;
	background-color: #2D2D2D;
	padding: 3px 5px;
}
.h_reply p {
	margin: 10px 0 5px;
}
.henv_left {
	float: left;
	width: 310px;
}
.henv_right {
	float: right;
	width: 180px;
}
.h_reply textarea {
	width: 298px; /* - 12px */
}
.h_t { text-align: right }
.h_t_left { float: left }
.h_boxes {
	line-height: 2em;
}
.h_boxes input {
	position: relative;
	top: 2px;
	margin-right: 3px;
}');
		
		echo '
<h1>Henvendelser</h1>
<p class="h_right"><a href="henvendelser?a">Tilbake</a></p>
<div class="section center w300">
	<h2>Informasjon</h2>
	<dl class="dl_30">
		<dt>Tittel</dt>
		<dd>'.htmlspecialchars($h['h_category']).': '.htmlspecialchars($h['h_subject']).'</dd>
		<dt>Status</dt>
		<dd>'.$status['crew'][$h['h_status']].'</dd>
		<dt>Innsender</dt>
		<dd>'.htmlspecialchars($h['h_name']).'<br />'.htmlspecialchars($h['h_email']).'</dd>
		<dt>Siste visning</dt>
		<dd>'.($h['h_last_visit'] ? $_base->date->get($h['h_last_visit'])->format() : 'Aldri').'</dd>
	</dl>
</div>
<div class="henvendelser_meldinger">';
		
		// forhåndsvisning?
		$preview_row = false;
		if ($preview)
		{
			$preview_row = array(
				"hm_time" => time(),
				"hm_ip" => $_SERVER['REMOTE_ADDR'],
				"hm_up_id" => login::$user->player->id,
				"hm_browser" => $_SERVER['HTTP_USER_AGENT'],
				"hm_content" => $content,
				"hm_type" => $crewonly ? 1 : 0,
				"hm_crew" => 1,
				"preview" => true
			);
		}
		
		while (($row = mysql_fetch_assoc($result)) || (($row = $preview_row) && !($preview_row = false)))
		{
			$preview = isset($row['preview']);
			
			echo '
	<div class="henvendelser_melding'.($row['hm_type'] == 1 ? ' hm_crew' : '').'"'.($preview ? ' id="scroll_here"' : '').'>
		<p class="hm_title"><span class="hm_by">'.($preview ? '<b>Forhåndsvisning:</b> ' : '').($row['hm_crew'] == 0 ? htmlspecialchars($h['h_name']) : 'Crew: <user id="'.$row['hm_up_id'].'" />').($row['hm_time'] > $h['h_last_visit'] ? ' (<b>IKKE SETT</b>)' : '').'</span> '.$_base->date->get($row['hm_time'])->format(date::FORMAT_SEC).'</p>
		<div class="p hm_content">'.($bb ? (($data = game::format_data($row['hm_content'])) == "" ? '<i>Mangler data.</i>' : $data) : '<pre>'.nl2br(htmlspecialchars($row['hm_content'])).'</pre>').'</div>
		'.($row['hm_type'] == 1 ? '<div class="hm_crewonly">Kun synlig for Crew</div>
		' : '').'<p class="hm_footer">IP-adresse: <a href="admin/brukere/finn?ip='.urlencode($row['hm_ip']).'">'.htmlspecialchars($row['hm_ip']).'</a><br />('.htmlspecialchars($row['hm_browser']).')</p>
	</div>';
		}
		
		echo '
</div>
<div class="h_reply">
	<p class="h_title">Behandle henvendelse</p>
	<p>Statusen på henvendelsen kan endres uten at det må legges til en besvarelse. I så fall må besvarelse feltet være helt tomt.</p>
	<form action="" method="post">
		<input type="hidden" name="reply" />
		<div class="henv_left">
			<p class="h_t"><span class="h_t_left">Besvarelse: (sender e-post)</span> <input type="checkbox" name="crewonly" id="crewonly"'.(isset($_POST['crewonly']) ? ' checked="checked"' : '').' /><label for="crewonly"> Synlig kun for Crew</label></p>
			<p><textarea name="content" rows="7" cols="50">'.htmlspecialchars(postval("content")).'</textarea></p>
		</div>
		<div class="henv_right">
			<p>Endre status:</p>';
		
		/*
		status:
			0 = ny (ikke behandlet)
				kun ved nye meldinger fra bruker
			1 = under behandling
				når som helst
			2 = venter på svar
				når som helst
				send e-post
			3 = lukket
				når som helst
				send e-post
			4 = slettet
				når som helst
				kan ikke sende e-post
		*/
		
		$boxes = array();
		
		// legg til de andre boksene
		$checked = false;
		$checked_id = postval("status");
		foreach ($status['crew'] as $id => $name)
		{
			if ($id == 0 || $id == $h['h_status']) continue;
			
			$suffix = $id == 2 || $id == 3 ? ' (sender e-post)' : '';
			$boxes[] = '<input type="radio" name="status" value="'.$id.'" id="status_'.$id.'"'.($checked_id == $id && ($checked = true) ? ' checked="checked"' : '').' /><label for="status_'.$id.'"> '.htmlspecialchars($name).'</label>'.$suffix;
		}
		
		// ingen endring (kan ikke være "ny" (id = 0))
		if ($h['h_status'] != 0) array_unshift($boxes, '<input type="radio" name="status" value="-1" id="status_none"'.(!$checked ? ' checked="checked"' : '').' /><label for="status_none"> Ingen endring ('.htmlspecialchars(mb_strtolower($status['crew'][$h['h_status']])).')</label>');
		
		echo '
			<p class="h_boxes">
				'.implode("<br />
				", $boxes).'
			</p>
		</div>
		<div class="clear"></div>
		<p class="c">'.show_sbutton("Utfør", 'name="add"').' '.show_sbutton("Forhåndsvis", 'name="preview"').'</p>
	</form>
</div>';
	}
	
	else
	{
		// hva skal vi vise?
		$show = array();
		foreach ($_GET as $var => $dummy)
		{
			$matches = false;
			if (preg_match("/^s([0-4])$/Du", $var, $matches))
			{
				$show[] = $matches[1];
			}
		}
		if (count($show) == 0) $show = array(0,1,2);
		
		// hent henvendelsene
		$result = $_base->db->query("SELECT h_id, h_name, h_category, h_email, h_subject, h_status, h_time FROM henvendelser WHERE h_status IN (".implode(",", $show).") ORDER BY h_hm_time DESC");
		
		if (mysql_num_rows($result) > 0)
		{
			$list = array();
			$ids = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$list[$row['h_id']] = $row;
				$ids[] = $row['h_id'];
			}
			$ids = implode(",", $ids);
			
			// hent siste svar og antall svar for hver henvendelse
			$result = $_base->db->query("SELECT hm_id, hm_h_id, hm_time, hm_crew, COUNT(hm_id) AS hm_count FROM (SELECT hm_id, hm_h_id, hm_time, hm_crew FROM henvendelser_messages WHERE hm_h_id IN ($ids) ORDER BY hm_time DESC) AS ref GROUP BY hm_h_id");
			while ($row = mysql_fetch_assoc($result))
			{
				$list[$row['hm_h_id']]['hm_time'] = $row['hm_time'];
				$list[$row['hm_h_id']]['hm_crew'] = $row['hm_crew'];
				$list[$row['hm_h_id']]['hm_count'] = $row['hm_count'];
			}
		}
		
		echo '
<h1>Henvendelser</h1>
<form action="" method="get">
	<input type="hidden" name="a" />
	<p class="c">Vis:';

		foreach ($status['crew'] as $id => $value)
		{
			echo ' <input type="checkbox" name="s'.$id.'"'.(in_array($id, $show) ? ' checked="checked"' : '').' id="s'.$id.'" /><label for="s'.$id.'"> '.htmlspecialchars($value).'</label>';
		}
		
		echo ' '.show_sbutton("Oppdater").'</p>
</form>';
		
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">Fant ingen henvendelser.</p>';
		}
		
		else
		{
			echo '
<table class="table center">
	<thead>
		<tr>
			<th>Navn</th>
			<th>Emne</th>
			<th>Innsendt</th>
			<th>Siste melding</th>
			<th>Ant</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>';
			
			$i = 0;
			foreach ($list as $row)
			{
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td title="'.htmlspecialchars($row['h_email']).'">'.htmlspecialchars($row['h_name']).'</td>
			<td><a href="henvendelser?a&amp;h_id='.$row['h_id'].'">'.htmlspecialchars($row['h_category']).': '.htmlspecialchars($row['h_subject']).'</a></td>
			<td>'.$_base->date->get($row['h_time'])->format().'</td>
			<td title="'.($row['hm_crew'] == 0 ? '(Av avsender)' : '(Av Kofradia)').'">'.$_base->date->get($row['hm_time'])->format().'</td>
			<td class="r">'.$row['hm_count'].'</td>
			<td>'.$status['crew'][$row['h_status']].'</td>
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
		}
	}
	
	$_base->page->load();
}

$_base->page->theme_file = "guest_simple";

$_base->page->add_css('
.h_info {
	padding: 3px 5px;
	margin: 10px 0;
	text-align: right;
	border-bottom: 2px solid #1F1F1F;
	font-size: 12px;
}
.h_info .h_title {
	float: left;
}
.henvendelser_melding {
	margin: 10px 0 15px;
}
.hm_title {
	margin: 0;
	background-color: #141414;
	padding: 3px 5px;
	text-align: right;
	color: #888888;
}
.hm_title .hm_by {
	float: left;
	color: #EEEEEE;
}
.hm_content {
	margin: 0;
	padding: 5px;
	background-color: #070707;
}
.hm_footer {
	margin: 0;
	padding: 3px 0;
	color: #444444;
	font-size: 10px;
	text-align: right;
}
.h_reply p {
	margin: 10px;
	text-align: center;
}
.h_reply textarea {
	margin: 0 auto;
	width: 300px;
	background-color: #0B0B0B;
}
/*.h_reply input {
	background-color: #0B0B0B;
}*/
');

// er vi logget inn?
$user = false;
if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email']))
{
	// for lenge siden siste visning?
	if ($_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_last_hit'] < time()-900)
	{
		unset($_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email'], $_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_last_hit']);
	}
	else
	{
		$user = $_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email'];
		$_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_last_hit'] = time();		
	}
}

// logge ut?
if (isset($_GET['logout']) && $user)
{
	unset($_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email']);
	$_base->page->add_message("Du er nå logget ut fra dine henvendelser.");
	redirect::handle();
}

// logge inn?
if (isset($_REQUEST['id']))
{
	// allerede logget inn?
	/*if ($user)
	{
		$_base->page->add_message("Allerede logget inn.", "error");
		redirect::handle();
	}*/
	
	if (!isset($_REQUEST['email']) && !$user)
	{
		$_base->page->add_message("Mangler e-post.", "error");
		redirect::handle();
	}
	
	$id = intval($_REQUEST['id']);
	$email = $user ? $user : requestval('email');
	
	// finn oppføringen
	$result = $_base->db->query("SELECT h_id, h_email FROM henvendelser WHERE h_random = $id AND h_email = ".$_base->db->quote($email)." AND h_status != 4 ORDER BY h_hm_time DESC LIMIT 1");
	$row = mysql_fetch_assoc($result);
	
	if (!$row)
	{
		$_base->page->add_message("Fant ikke oppføringen.", "error");
		redirect::handle();
	}
	
	else
	{
		if (!$user)
		{
			$_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email'] = $row['h_email'];
			$_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_last_hit'] = time();
		}
		
		#$_base->page->add_message("Du er nå logget inn som ".htmlspecialchars($row['h_email']).".");
		redirect::handle("henvendelser?h_id={$row['h_id']}");
	}
}

// ny henvendelse?
$preview = false;
if (isset($_POST['new']) && (isset($_POST['add']) || isset($_POST['preview'])))
{
	$category = postval("category");
	$name = trim(postval("name"));
	$email = $user ? $user : trim(postval("email"));
	$subject = trim(postval("subject"));
	$content = trim(postval("content"));
	
	// sjekk kategori
	if (!isset($categories[$category]))
	{
		$_base->page->add_message("Du må velge en kategori.", "error");
	}
	
	// sjekk navn
	elseif (mb_strlen($name) == 0)
	{
		$_base->page->add_message("Du må fylle ut et navn.", "error");
	}
	
	// sjekk e-post
	elseif (!game::validemail($email))
	{
		$_base->page->add_message("Du må skrive inn en gyldig e-postadresse.", "error");
	}
	
	// sjekk emne
	elseif (mb_strlen($subject) == 0)
	{
		$_base->page->add_message("Du må fylle ut et emne.", "error");
	}
	
	// sjekk innhold
	elseif (mb_strlen($content) < 20)
	{
		$_base->page->add_message("Henvendelsen kan ikke inneholde mindre enn 20 tegn.", "error");
	}
	
	elseif (isset($_POST['preview']))
	{
		$preview = true;
		$_base->page->add_message("Viser forhåndsvisning.");
	}
	
	else
	{
		$_base->db->begin();
		
		// legg til hovedoppføring
		$random = rand(10000, 99999);
		$_base->db->query("INSERT INTO henvendelser SET h_name = ".$_base->db->quote($name).", h_category = ".$_base->db->quote($categories[$category]).", h_email = ".$_base->db->quote($email).", h_subject = ".$_base->db->quote($subject).", h_time = ".time().", h_hm_time = ".time().", h_random = $random");
		$h_id = $_base->db->insert_id();
		
		// legg til meldingen
		$_base->db->query("INSERT INTO henvendelser_messages SET hm_h_id = $h_id, hm_content = ".$_base->db->quote($content).", hm_time = ".time().", hm_ip = ".$_base->db->quote($_SERVER['REMOTE_ADDR']).", hm_up_id = ".(login::$logged_in ? login::$user->player->id : "NULL").", hm_browser = ".$_base->db->quote($_SERVER['HTTP_USER_AGENT']));
		$hm_id = $_base->db->insert_id();
		
		$_base->db->commit();
		
		// oppdater cache
		tasks::set("henvendelser", mysql_result($_base->db->query("SELECT COUNT(h_id) FROM henvendelser WHERE h_status = 0"), 0));
		
		// send e-post til bruker
		$mail = new email();
		$mail->text = "Hei $name,

Din henvendelse er nå levert til Kofradia Crewet. Henvendelsen vil bli besvart hvis det er behov for det, og du vil motta e-post når det blir gjort endringer til din henvendelse.

Du kan når som helst logge inn og lese din henvendelse og legge til ytterligere informasjon og nye meldinger. Da er du nødt til å logge inn med en spesiell ID sammen med e-posten din.

Din ID er: $random
E-post registert: $email
IP-adresse benyttet: {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})

Direktelink:
{$__server['path']}/henvendelser?id=$random&email=".urlencode($email)."

Kategori: {$categories[$category]}
Emne: $subject

------
$content
------

Denne e-posten kan ikke besvares.
Takk for din henvendelse.";
		$mail->send($email, "Henvendelse mottatt - {$categories[$category]}: $subject");
		
		// send e-post til henrik
		$mail = new email();
		$mail->text = "Henvendelse mottatt fra {$_SERVER['REMOTE_ADDR']} ".$_base->date->get()->format(date::FORMAT_SEC).":

Nettleser: {$_SERVER['HTTP_USER_AGENT']}
E-post: $email
IP-adresse: {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})

Direktelink: {$__server['path']}/henvendelser?a&h_id=$h_id

Kategori: {$categories[$category]}
Emne: $subject

------
$content
------";
		$mail->send("henrist@henrist.net", "Henvendelse mottatt - {$categories[$category]}: $subject");
		
		if ($user)
		{
			$_base->page->add_message("Din henvendelse er levert. Sjekk også e-posten din.");
		}
		else
		{
			$_base->page->add_message("Din henvendelse er levert. Sjekk e-posten din.");
		}
		
		redirect::handle("henvendelser?highlight=$h_id");
	}
}


// logget inn?
if ($user)
{
	echo '
<h1>Mine henvendelser</h1>
<p class="h_right"><a href="henvendelser">Forsiden</a> <a href="henvendelser?logout">Logg ut</a></p>
<p class="c">Du er logget inn som '.htmlspecialchars($user).'.</p>';
	
	// ny henvendelse?
	if (isset($_GET['new']))
	{
		echo '
<div class="section w350 center">
	<h2>Ny henvendelse</h2>
	<p>Husk at hvis denne henvendelsen er relatert noen andre av dine henvendelser er det bedre at du legger inn <u>svar</u> i de henvendelsene <u>enn at du oppretter en ny henvendelse</u>.</p>
	<p><u>Det kan ta tid før dine henvendelser blir besvart.</u> Respekter dette og ikke send inn gjentatte nye henvendelser.</p>
	<p>Hvis henvendelsen ikke er markert som <u>ferdig behandlet</u>, vil den hele tiden komme opp i vårt system.</p>
	<form action="" method="post" autocomplete="off">
		<input type="hidden" name="new" />
		<dl class="dl_30 dl_2x">
			<dt>Kategori</dt>
			<dd>
				<select name="category">';
	
$selected = postval("category", false);
if (!isset($categories[$selected]))
{
	echo '
					<option value="">Velg kategori</option>';
}
foreach ($categories as $id => $kategori)
{
	echo '
					<option value="'.$id.'"'.($selected == $id ? ' selected="selected"' : '').'>'.htmlspecialchars($kategori).'</option>';
}

echo '
				</select>
			</dd>
			<dt>Navn</dt>
			<dd><input type="text" name="name" value="'.htmlspecialchars(postval("name")).'" id="kontakt_navn" maxlength="30" class="styled w150" /></dd>
			<dt>Kort emne</dt>
			<dd><input type="text" name="subject" value="'.htmlspecialchars(postval("subject")).'" class="styled w150" /></dd>
			<dt>Din henvendelse</dt>
			<dd><textarea name="content" rows="10" cols="35">'.htmlspecialchars(postval("content")).'</textarea></dd>'.($preview ? '
			<dt>Forhåndsvisning</dt>
			<dd>'.game::format_data($content).'</dd>' : '').'
		</dl>
		<p class="c">'.show_sbutton("Send inn henvendelse", 'name="add"').' '.show_sbutton("Forhåndsvisning", 'name="preview"').'</p>
	</form>
</div>';
		$_base->page->load();
	}
	
	// bestemt henvendelse?
	if (isset($_GET['h_id']))
	{
		$h_id = intval($_GET['h_id']);
		$bb = true;
		
		// hent henvendelsen
		$result = $_base->db->query("SELECT h_id, h_email, h_category, h_subject, h_name, h_status, h_time FROM henvendelser WHERE h_id = $h_id AND h_email = ".$_base->db->quote($user)." AND h_status != 4");
		$h = mysql_fetch_assoc($result);
		
		if (!$h)
		{
			$_base->page->add_message("Fant ikke henvendelsen.", "error");
			redirect::handle();
		}
		
		// oppdater
		$_base->db->query("UPDATE henvendelser SET h_last_visit = ".time()." WHERE h_id = $h_id");
		
		// opprette svar?
		$preview = false;
		if (isset($_POST['reply']) && (isset($_POST['add']) || isset($_POST['preview'])))
		{
			$content = trim(postval("content"));
			
			if (mb_strlen($content) == 0)
			{
				$_base->page->add_message("Mangler innhold.", "error");
			}
			
			elseif (isset($_POST['preview']))
			{
				$preview = true;
				$_base->page->add_message("Viser forhåndsvisning");
			}
			
			else
			{
				$_base->db->query("INSERT INTO henvendelser_messages SET hm_h_id = $h_id, hm_time = ".time().", hm_ip = ".$_base->db->quote($_SERVER['REMOTE_ADDR']).", hm_up_id = ".(login::$logged_in ? login::$user->player->id : "NULL").", hm_browser = ".$_base->db->quote($_SERVER['HTTP_USER_AGENT']).", hm_content = ".$_base->db->quote($content));
				$_base->page->add_message("Meldingen ble lagt til.");
				
				// oppdatere henvendelsen
				$_base->db->query("UPDATE henvendelser SET h_status = 0, h_hm_time = ".time()." WHERE h_id = $h_id");
				
				// oppdater cache
				tasks::set("henvendelser", mysql_result($_base->db->query("SELECT COUNT(h_id) FROM henvendelser WHERE h_status = 0"), 0));
				
				// send e-post til henrik
				$mail = new email();
				$mail->text = "Ny melding i henvendelse:

Navn: {$h['h_name']}
E-post: {$h['h_email']}
Tidspunkt: ".$_base->date->get()->format(date::FORMAT_SEC)."

IP-adresse: {$_SERVER['REMOTE_ADDR']}
Nettleser: {$_SERVER['HTTP_USER_AGENT']}

Direktelink: {$__server['path']}/henvendelser?a&h_id=$h_id

Kategori: {$h['h_category']}
Emne: {$h['h_subject']}

------
$content
------";
				$mail->send("henrist@henrist.net", "Ny melding i henvendelse mottatt - {$h['h_category']}: {$h['h_subject']}");
				
				redirect::handle("henvendelser?h_id=$h_id");
			}
		}
		
		// hent alle meldingene
		$result = $_base->db->query("SELECT hm_time, hm_ip, hm_browser, hm_content, hm_crew FROM henvendelser_messages WHERE hm_h_id = $h_id AND hm_type = 0 ORDER BY hm_time");
		
		echo '
<p class="h_info"><span class="h_title">'.htmlspecialchars($h['h_category']).': '.htmlspecialchars($h['h_subject']).'</span> '.$status['other'][$h['h_status']].'</p>
<div class="henvendelser_meldinger">';
		
		// forhåndsvisning?
		$preview_row = false;
		if ($preview)
		{
			$preview_row = array(
				"hm_time" => time(),
				"hm_ip" => $_SERVER['REMOTE_ADDR'],
				"hm_browser" => $_SERVER['HTTP_USER_AGENT'],
				"hm_content" => $content,
				"hm_crew" => 0,
				"preview" => true
			);
		}

		while (($row = mysql_fetch_assoc($result)) || (($row = $preview_row) && !($preview_row = false)))
		{
			$preview = isset($row['preview']);
			
			echo '
	<div class="henvendelser_melding"'.($preview ? ' id="scroll_here"' : '').'>
		<p class="hm_title"><span class="hm_by">'.($preview ? '<b>Forhåndsvisning:</b> ' : '').($row['hm_crew'] == 0 ? htmlspecialchars($h['h_name']) : '<b>Besvarelse</b>').'</span> '.$_base->date->get($row['hm_time'])->format(date::FORMAT_SEC).'</p>
		<div class="p hm_content">'.($bb ? game::format_data($row['hm_content']) : '<pre>'.nl2br(htmlspecialchars($row['hm_content'])).'</pre>').'</div>'.($row['hm_crew'] == 0 ? '
		<p class="hm_footer">IP-adresse: '.htmlspecialchars($row['hm_ip']).'<br />('.htmlspecialchars($row['hm_browser']).')</p>' : '').'
	</div>';
		}
		
		echo '
</div>
<div class="h_reply">
	<form action="" method="post">
		<input type="hidden" name="reply" />
		<p><textarea name="content" rows="7" cols="50">'.htmlspecialchars(postval("content")).'</textarea></p>
		<p>'.show_sbutton("Legg til melding", 'name="add"').' '.show_sbutton("Forhåndsvis", 'name="preview"').'</p>
	</form>
</div>';
	}
	
	else
	{
		// hent henvendelsene
		$result = $_base->db->query("SELECT h_id, h_category, h_subject, h_status, h_time FROM henvendelser WHERE h_email = ".$_base->db->quote($user)." AND h_status != 4 ORDER BY h_hm_time DESC");
		
		if (mysql_num_rows($result) == 0)
		{
			// alle henvendelsene til denne brukeren er slettet
			unset($_SESSION[$GLOBALS['__server']['session_prefix'].'henvendelser_email']);
			redirect::handle();
		}
		
		$list = array();
		$ids = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$list[$row['h_id']] = $row;
			$ids[] = $row['h_id'];
		}
		
		$ids = implode(",", $ids);
		
		// hent siste svar og antall svar for hver henvendelse
		$result = $_base->db->query("SELECT hm_id, hm_h_id, hm_time, hm_crew, COUNT(hm_id) AS hm_count FROM (SELECT hm_id, hm_h_id, hm_time, hm_crew FROM henvendelser_messages WHERE hm_h_id IN ($ids) AND hm_crew = 0 ORDER BY hm_time DESC) AS ref GROUP BY hm_h_id");
		while ($row = mysql_fetch_assoc($result))
		{
			$list[$row['hm_h_id']]['hm_time'] = $row['hm_time'];
			$list[$row['hm_h_id']]['hm_crew'] = $row['hm_crew'];
			$list[$row['hm_h_id']]['hm_count'] = $row['hm_count'];
		}
		
		echo '
<p class="c"><a href="henvendelser?new">Ny henvendelse</a></p>
<table class="table center">
	<thead>
		<tr>
			<th>Emne</th>
			<th>Tidspunkt</th>
			<th>Siste melding</th>
			<th>Meldinger</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>';
		
		$i = 0;
		$highlight = getval("highlight");
		foreach ($list as $row)
		{
			++$i;
			
			echo '
		<tr'.($row['h_id'] == $highlight ? ' class="highlight"' : ($i % 2 == 0 ? ' class="color"' : '')).'>
			<td><a href="henvendelser?h_id='.$row['h_id'].'">'.htmlspecialchars($row['h_category']).': '.htmlspecialchars($row['h_subject']).'</a></td>
			<td>'.$_base->date->get($row['h_time'])->format().'</td>
			<td title="'.($row['hm_crew'] == 0 ? '(Av meg)' : '(Av Kofradia)').'">'.$_base->date->get($row['hm_time'])->format().'</td>
			<td>'.$row['hm_count'].'</td>
			<td>'.$status['other'][$row['h_status']].'</td>
		</tr>';
		}
		
		echo '
	</tbody>
</table>';
	}
	
	$_base->page->load();
}


// glemt id?
if (isset($_GET['forgot']))
{
	// sende e-post?
	if (isset($_POST['email']))
	{
		$email = trim(postval("email"));
		
		// hent henvendelsene med denne e-posten
		$result = $_base->db->query("SELECT h_id, h_name, h_email, h_category, h_subject, h_status, h_time, h_random FROM henvendelser WHERE h_email = ".$_base->db->quote($email)." AND h_status != 4 ORDER BY h_time");
		
		if (mysql_num_rows($result) == 0)
		{
			$_base->page->add_message("Fant ingen henvendelser med denne e-posten.", "error");
		}
		
		else
		{
			$list = array();
			$hm_id = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				$list[] = "ID: {$row['h_random']}\nTidspunkt: ".$_base->date->get($row['h_time'])->format()."\n{$row['h_category']}: {$row['h_subject']}";
				$random = $row['h_random'];
			}
			
			// send e-post til bruker
			$mail = new email();
			$mail->text = "Hei,

Du har bedt om en oversikt over dine henvendelser som er sendt inn til Kofradia.

Du kan når som helst logge inn og lese dine henvendelse og legge til ytterligere informasjon og nye meldinger. Da er du nødt til å logge inn med en spesiell ID sammen med e-posten din.

---

".implode("

---

", $list)."

---

IP-adresse benyttet for denne henvendelsen: {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})

Direktelink:
{$__server['path']}/henvendelser?id={$random}&email=".urlencode(mb_strtolower($email))."

Denne e-posten kan ikke besvares.
Takk for din henvendelse.";
			$mail->send(mb_strtolower($email), "Dine henvendelser");
			
			$_base->page->add_message("E-post er nå sendt til <b>".htmlspecialchars(mb_strtolower($email))."</b> med detaljer.");
			redirect::handle("henvendelser");
		}
	}
	
	echo '
<h1>Henvendelser</h1>
<p class="h_right"><a href="henvendelser">Tilbake</a></p>
<div class="section w300 center">
	<h2>Glemt ID</h2>
	<p>Ved å benytte dette skjemaet vil du få tilsendt liten oversikt over alle dine henvendelser og ID-er tilknyttet de.</p>
	<form action="" method="post" autocomplete="off">
		<dl class="dd_right dl_2x">
			<dt>E-postadresse</dt>
			<dd><input type="text" name="email" value="'.htmlspecialchars(postval("email")).'" class="styled w150" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Send oversikt").'</p>
	</form>
</div>';
	
	$_base->page->load();
}






echo '
<div class="section w350 center">
	<h2>Ny henvendelse</h2>
	<p>Har du allerede sendt inn henvendelse? Bruk <a href="henvendelser?forgot">denne siden</a> til å motta link. <u>Svar</u> i en evt. innsendt henvendelse, i stedet for å opprette ny.</p>
	<p><u>Det kan ta tid før dine henvendelser blir besvart.</u> Respekter dette og ikke send inn gjentatte nye henvendelser.</p>
	<p>Hvis henvendelsen ikke er markert som <u>ferdig behandlet</u>, vil den hele tiden komme opp i vårt system.</p>
	<form action="" method="post" autocomplete="off">
		<input type="hidden" name="new" />
		<dl class="dl_30 dl_2x">
			<dt>Kategori</dt>
			<dd>
				<select name="category">';
	
$selected = postval("category", false);
if (!isset($categories[$selected]))
{
	echo '
					<option value="">Velg kategori</option>';
}
foreach ($categories as $id => $kategori)
{
	echo '
					<option value="'.$id.'"'.($selected == $id ? ' selected="selected"' : '').'>'.htmlspecialchars($kategori).'</option>';
}

echo '
				</select>
			</dd>
			<dt>Navn</dt>
			<dd><input type="text" name="name" value="'.htmlspecialchars(postval("name")).'" id="kontakt_navn" maxlength="30" class="styled w150" /></dd>
			<dt>E-postadresse</dt>
			<dd><input type="text" name="email" value="'.htmlspecialchars(postval("email")).'" class="styled w150" /></dd>
			<dt>Kort emne</dt>
			<dd><input type="text" name="subject" value="'.htmlspecialchars(postval("subject")).'" class="styled w150" /></dd>
			<dt>Din henvendelse</dt>
			<dd><textarea name="content" rows="10" cols="35">'.htmlspecialchars(postval("content")).'</textarea></dd>'.($preview ? '
			<dt>Forhåndsvisning</dt>
			<dd>'.game::format_data($content).'</dd>' : '').'
		</dl>
		<p class="c">'.show_sbutton("Send inn henvendelse", 'name="add"').' '.show_sbutton("Forhåndsvis", 'name="preview"').'</p>
	</form>
</div>
<div class="section w350 center">
	<h2>Mine henvendelser</h2>
	<p class="j">For å få tilgang til henvendelsene du har sendt inn må du logge inn. Du får ny ID ved hver henvendelse, men alle ID-ene gir deg tilgang til alle henvendelsene.</p>
	<form action="" method="post" autocomplete="off">
		<dl class="dl_30 dl_2x">
			<dt>ID - <a href="henvendelser?forgot">Glemt ID?</a></dt>
			<dd><input type="text" name="id" value="'.htmlspecialchars(postval("id")).'" class="styled w40" /></dd>
			<dt>E-postadresse</dt>
			<dd><input type="text" name="email" value="'.htmlspecialchars(postval("email")).'" class="styled w150" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Vis mine henvendelser").'</p>
	</form>
</div>';

$_base->page->load();