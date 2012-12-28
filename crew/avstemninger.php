<?php

require dirname(dirname(__FILE__))."/base/mod.php_source.php";

// krev at dette scriptet hentes av dynamic.php
if (!defined("BASE_LOADED")) die("No direct access.");

global $__server, $pages, $_base; // $pages har data fra [2] og ut

// administrere avstemninger for crewet
access::need("forum_mod");
$_base->page->add_title("Administrasjon");

// kontroller at session id er med og er gyldig
function verify_sid()
{
	if (!isset($_GET['sid']) || $_GET['sid'] != login::$info['ses_id']) return false;
	return true;
}

redirect::store("/polls/admin", redirect::ROOT);

// aktuell avstemning?
if (isset($pages[2]) && preg_match("/^\\d+\$/D", $pages[2]))
{
	// hent avstemningen
	$result = $_base->db->query("SELECT p_id, p_title, p_text, p_ft_id, p_params, p_active, p_time_start, p_time_end, p_votes FROM polls WHERE p_id = ".intval($pages[2]));
	if (mysql_num_rows($result) == 0)
	{
		$_base->page->add_message("Fant ikke avstemningen.", "error");
		redirect::handle();
	}
	
	$poll = mysql_fetch_assoc($result);
	$_base->page->add_title($poll['p_title']);
	
	// hent alternativene
	$poll['options'] = array();
	$result = $_base->db->query("SELECT po_id, po_text, po_votes FROM polls_options WHERE po_p_id = {$poll['p_id']}");
	while ($row = mysql_fetch_assoc($result))
	{
		$poll['options'][$row['po_id']] = $row;
	}
	
	redirect::store("/polls/admin/{$poll['p_id']}");
	
	// TODO: slette stemmer
	
	// rediger avstemning?
	if (isset($pages[3]) && $pages[3] == "edit")
	{
		// lagre endringer?
		if (isset($_POST['title']) && verify_sid())
		{
			$title = trim(postval("title"));
			$text = trim(postval("text"));
			$time_start = trim(postval("time_start"));
			$time_end = trim(postval("time_end"));
			$error = false;
			
			if (strlen($title) < 3)
			{
				$_base->page->add_message("Tittelen må inneholde minst 3 tegn.", "error");
				$error = true;
			}
			
			if (!$error && $time_start != "")
			{
				$time_start_m = check_date($time_start, "%d\\.%m\\.%y %h:%i");
				if (count($time_start_m) == 0)
				{
					$_base->page->add_message("Formatet for dato start var feil.", "error");
					$error = true;
				}
				else
				{
					$date = $_base->date->get();
					$date->setTime($time_start_m[4], $time_start_m[5], 0);
					$date->setDate($time_start_m[3], $time_start_m[2], $time_start_m[1]);
					$time_start = $date->format("U");
				}
			}
			
			if (!$error && $time_end != "")
			{
				$time_end_m = check_date($time_end, "%d\\.%m\\.%y %h:%i");
				if (count($time_end_m) == 0)
				{
					$_base->page->add_message("Formatet for dato slutt var feil.", "error");
					$error = true;
				}
				else
				{
					$date = $_base->date->get();
					$date->setTime($time_end_m[4], $time_end_m[5], 0);
					$date->setDate($time_end_m[3], $time_end_m[2], $time_end_m[1]);
					$time_end = $date->format("U");
				}
			}
			
			if (!$error)
			{
				// oppdater
				$_base->db->query("UPDATE polls SET p_title = ".$_base->db->quote($title).", p_text = ".$_base->db->quote($text).", p_time_start = ".intval($time_start).", p_time_end = ".intval($time_end)." WHERE p_id = {$poll['p_id']}");
				$_base->page->add_message("Endringene ble lagret.");
				
				// slett cache
				cache::delete("polls_list");
				
				redirect::handle();
			}
		}
		
		$_base->page->add_js('
function setNow(elm, event)
{
	var d = new Date();
	elm.set("value", str_pad(d.getDate())+"."+str_pad(d.getMonth()+1)+"."+d.getFullYear()+" "+str_pad(d.getHours())+":"+str_pad(d.getMinutes()));
	event.stop();
}');
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Endre avstemning<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/edit?sid='.login::$info['ses_id'].'" method="post">
			<p>Du endrer avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;.</p>
			<p><b>Aktiv:</b> '.($poll['p_active'] == 0 ? 'Nei' : 'Ja').'</p>
			<p><b>Tittel:</b></p>
			<p><input type="text" name="title" class="styled w200" value="'.htmlspecialchars(postval("title", $poll['p_title'])).'" /></p>
			<p><b>Beskrivelse:</b></p>
			<p><textarea name="text" style="width: 90%" rows="15">'.htmlspecialchars(postval("text", $poll['p_text'])).'</textarea></p>
			<p><b>Dato/tid start:</b> (<a href="#" onclick="setNow($(\'time_start\'), event)">Sett inn nåværende tidspunkt</a>)</p>
			<p><input type="text" name="time_start" id="time_start" class="styled w100" value="'.htmlspecialchars(postval("time_start", empty($poll['p_time_start']) ? '' : $_base->date->get($poll['p_time_start'])->format("d.m.Y H:i"))).'" /></p>
			<p><b>Dato/tid slutt:</b> (<a href="#" onclick="setNow($(\'time_end\'), event)">Sett inn nåværende tidspunkt</a>)</p>
			<p><input type="text" name="time_end" id="time_end" class="styled w100" value="'.htmlspecialchars(postval("time_end", empty($poll['p_time_end']) ? '' : $_base->date->get($poll['p_time_end'])->format("d.m.Y H:i"))).'" /></p>
			<p>Dato/tid må være i formatet <u>dag<b>.</b>måned<b>.</b>år time<b>:</b>minutt</u>, eksempel: 2.11.2008 22:33.</p>
			<p>'.show_sbutton("Lagre endringer").' <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	// rediger forum malen?
	if (isset($pages[3]) && $pages[3] == "forum_template")
	{
		// finnes allerede en forum tråd?
		if ($poll['p_ft_id'])
		{
			$_base->page->add_message("Det er allerede en forum tråd tilknyttet denne avstemningen.");
			redirect::handle();
		}
		
		// lagre endringer?
		if (isset($_POST['text']) && verify_sid())
		{
			$text = trim(postval("text"));
			$active = isset($_POST['active']);
			
			// sette en annen bruker ID enn den som redigerer teksten?
			if (access::has("admin") && postval("up_id") != "0" && postval("up_id") != "")
			{
				$up_id = intval(postval("up_id"));
				
				// kontroller at brukeren finnes
				$result = $_base->db->query("SELECT up_name FROM users_players WHERE up_id = $up_id");
				if (mysql_num_rows($result) == 0)
				{
					$_base->page->add_message("Fant ikke spilleren med ID $up_id. Bruker din ID.", "error");
					$up_id = login::$user->player->id;
				}
			}
			else
			{
				$up_id = login::$user->player->id;
			}
			
			$params = new params($poll['p_params']);
			$params->update("forum_active", $active ? 1 : 0);
			$params->update("forum_text", $text);
			$params->update("forum_up_id", $up_id);
			$params = $params->build();
			
			// oppdater
			$_base->db->query("UPDATE polls SET p_params = ".$_base->db->quote($params)." WHERE p_id = {$poll['p_id']}");
			$_base->page->add_message("Endringene ble lagret.");
			
			// slett cache
			cache::delete("polls_list");
			
			redirect::handle();
		}
		
		$params = new params($poll['p_params']);
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Endre forum mal<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/forum_template?sid='.login::$info['ses_id'].'" method="post">
			<p>Du endrer avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;. ('.($poll['p_active'] == 0 ? 'Ikke aktiv' : '<b>Aktiv</b>').')</p>
			<p><label for="active"><b>Aktiver opprettelse av forum emne</b> </label><input type="checkbox" name="active"'.($params->get("forum_active") ? ' checked="checked"' : '').' id="active" /></p>
			<p><b>Tittel:</b> &laquo;Avstemning: '.htmlspecialchars($poll['p_title']).'&raquo;</p>
			<p><b>Innhold til forum tråden:</b></p>
			<p><textarea name="text" style="width: 90%" rows="15">'.htmlspecialchars(postval("text", $params->get("forum_text"))).'</textarea></p>
			<p><b>Opprettes av ('.($params->get("forum_up_id") ? '<user id="'.$params->get("forum_up_id").'" />' : 'Ikke satt').'):</b></p>
			<p>'.(access::has("admin") ? 'Bruker ID: <input type="text" name="up_id" class="styled w100" value="'.htmlspecialchars($params->get("forum_up_id")).'" />' : '<user id="'.login::$user->player->id.'" />').'</p>
			<p>'.show_sbutton("Lagre endringer").' <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	// slette avstemning
	if (isset($pages[3]) && $pages[3] == "delete")
	{
		// har avstemningen noen alternativer?
		if (count($poll['options']) > 0)
		{
			$_base->page->add_message("Du kan ikke slette en avstemning med alternativer. Slett alternativene først.", "error");
			redirect::handle();
		}
		
		// slette?
		if (isset($_POST['delete']) && verify_sid())
		{
			// slett
			$_base->db->query("DELETE FROM polls WHERE p_id = {$poll['p_id']}");
			$_base->page->add_message("Avstemningen ble slettet.");
			
			// slett cache
			cache::delete("polls_list");
			
			redirect::handle();
		}
		
		$bb = game::bb_to_html($poll['p_text']);
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Slett avstemning<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/delete?sid='.login::$info['ses_id'].'" method="post">
			<p>Du er i ferd med å slette avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;.</p>'.(!empty($bb) ? '
			<p><b>Beskrivelse:</b></p>
			<div style="border: 1px solid #1F1F1F; padding: 0 10px">
				<div class="p">'.$bb.'</div>
			</div>' : '').'
			<p><span class="red">'.show_sbutton("Slett avstemning", 'name="delete"').'</span> <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	// sette aktiv?
	if (isset($pages[3]) && $pages[3] == "active" && verify_sid())
	{
		// har vi ingen alternativer?
		if (count($poll['options']) == 0)
		{
			$_base->page->add_message("Avstemningen må inneholde minimum 2 alternativer før den kan aktiveres.", "error");
			redirect::handle();
		}
		
		// allerede aktiv?
		if ($poll['p_active'] != 0) redirect::handle();
		
		// sett aktiv
		$_base->db->query("UPDATE polls SET p_active = 1 WHERE p_id = {$poll['p_id']}");
		$_base->page->add_message("Avstemningen er nå satt til aktiv.");
		
		// slett cache
		cache::delete("polls_list");
		
		redirect::handle();
	}
	
	// sette innaktiv?
	if (isset($pages[3]) && $pages[3] == "inactive" && verify_sid())
	{
		// ikke aktiv?
		if ($poll['p_active'] == 0) redirect::handle();
		
		// sett inaktiv
		$_base->db->query("UPDATE polls SET p_active = 0 WHERE p_id = {$poll['p_id']}");
		$_base->page->add_message("Avstemningen er ikke lengre aktiv.");
		
		// slett cache
		cache::delete("polls_list");
		
		redirect::handle();
	}
	
	// legge til alternativ?
	if (isset($pages[3]) && $pages[3] == "new")
	{
		// legge til?
		if (isset($_POST['text']) && verify_sid())
		{
			$text = trim(postval("text"));
			if (strlen($text) < 1)
			{
				$_base->page->add_message("Alternativet må inneholde minst 1 tegn.", "error");
			}
			
			else
			{
				// legg til
				$_base->db->query("INSERT INTO polls_options SET po_p_id = {$poll['p_id']}, po_text = ".$_base->db->quote($text));
				$_base->page->add_message("Alternativet ble lagt til.");
				
				// slett cache
				cache::delete("polls_list");
				
				redirect::handle();
			}
		}
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Nytt alternativ<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/new?sid='.login::$info['ses_id'].'" method="post">
			<p>Du legger til alternativ til avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;.</p>
			<p><b>Innhold til alternativet:</b></p>
			<p><textarea name="text" style="width: 90%" rows="2">'.htmlspecialchars(postval("text")).'</textarea></p>
			<p>'.show_sbutton("Legg til alternativ").' <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	// alternativ?
	$po_id = isset($pages[3]) && preg_match("/^\\d+\$/D", $pages[3]) ? (int) $pages[3] : false;
	$option = isset($pages[4]) ? $pages[4] : false;
	
	// redigere alternativ?
	if ($po_id && !$option)
	{
		// finnes ikke?
		if (!isset($poll['options'][$po_id]))
		{
			$_base->page->add_message("Fant ikke alternativet.", "error");
			redirect::handle();
		}
		
		$po = $poll['options'][$po_id];
		
		// lagre endringer?
		if (isset($_POST['text']) && verify_sid())
		{
			$text = trim(postval("text"));
			if (strlen($text) < 1)
			{
				$_base->page->add_message("Alternativet må inneholde minst 1 tegn.", "error");
			}
			
			else
			{
				// oppdater
				$_base->db->query("UPDATE polls_options SET po_text = ".$_base->db->quote($text)." WHERE po_id = $po_id");
				$_base->page->add_message("Alternativet ble oppdatert.");
				
				// slett cache
				cache::delete("polls_list");
				
				redirect::handle();
			}
		}
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Rediger alternativ<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/'.$po_id.'?sid='.login::$info['ses_id'].'" method="post">
			<p>Du redigerer et alternativ som tilhører avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;.</p>
			<p><b>Antall stemmer</b> dette alternativet har: '.game::format_number($po['po_votes']).'</p>
			<p><b>Nåvære innhold:</b></p>
			<div class="p">'.game::bb_to_html($po['po_text']).'</div>
			<p><b>Nytt innhold:</b></p>
			<p><textarea name="text" style="width: 90%" rows="2">'.htmlspecialchars(postval("text", $po['po_text'])).'</textarea></p>
			<p>'.show_sbutton("Lagre endringer").' <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	// slette alternativ?
	if ($po_id && $option == "delete")
	{
		// finnes ikke?
		if (!isset($poll['options'][$po_id]))
		{
			$_base->page->add_message("Fant ikke alternativet.", "error");
			redirect::handle();
		}
		
		$po = $poll['options'][$po_id];
		
		// har alternativet noen stemmer?
		if ($po['po_votes'] > 0)
		{
			$_base->page->add_message("Du kan ikke slette et alternativ som har stemmer.", "error");
			redirect::handle();
		}
		
		// avstemningen er aktiv og dette er det siste alternativet?
		if ($poll['p_active'] != 0 && count($poll['options']) == 2)
		{
			$_base->page->add_message("Avstemningen er aktiv og må inneholde minimum 2 alternativer. Du kan derfor ikke slette dette alternativet.", "error");
			redirect::handle();
		}
		
		// slette?
		if (isset($_POST['delete']) && verify_sid())
		{
			// slett
			$_base->db->query("DELETE FROM polls_options WHERE po_id = $po_id");
			$_base->page->add_message("Alternativet ble slettet.");
			
			// slett cache
			cache::delete("polls_list");
			
			redirect::handle();
		}
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Slett alternativ<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/'.$po_id.'/delete?sid='.login::$info['ses_id'].'" method="post">
			<p>Du er i ferd med å slette alternativ som tilhører avstemningen &laquo;'.htmlspecialchars($poll['p_title']).'&raquo;.</p>
			<p><b>Innhold:</b></p>
			<div class="p">'.game::bb_to_html($po['po_text']).'</div>
			<p><span class="red">'.show_sbutton("Slett alternativ", 'name="delete"').'</span> <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'">Avbryt</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	
	// vis informasjon
	$bb = game::bb_to_html($poll['p_text']);
	echo '
<div class="bg1_c small">
	<h1 class="bg1">Avstemning: '.htmlspecialchars($poll['p_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p><b>Tittel:</b><br />'.htmlspecialchars($poll['p_title']).'</p>
		<p><b>Status:</b><br />'.($poll['p_active'] == 0 ? 'Deaktivert' : 'Synlig').' ('.game::format_number($poll['p_votes']).' '.fword("stemme", "stemmer", $poll['p_votes']).')</p>
		<p><b>Tidsperiode:</b><br />'.(empty($poll['p_time_start']) ? 'Til '.(empty($poll['p_time_end']) ? 'ubestemt' : $_base->date->get($poll['p_time_end'])->format()) : 'Fra '.$_base->date->get($poll['p_time_start'])->format().' til '.(empty($poll['p_time_end']) ? 'ubestemt' : $_base->date->get($poll['p_time_end'])->format())).(!empty($bb) ? '
		<p><b>Beskrivelse:</b></p>
		<div style="border: 1px solid #1F1F1F; padding: 0 10px">
			<div class="p">'.$bb.'</div>
		</div>' : '').'
		<p><b>Alternativer:</b>'.(count($poll['options']) == 0 ? '<br />Ingen alternativer er opprettet. <a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/new">Opprett &raquo;</a>' : '').'</p>';
	
	if (count($poll['options']) > 0)
	{
		echo '
		<ul>';
		
		foreach ($poll['options'] as $row)
		{
			echo sprintf('
			<li>%s (%s) [<a href="'.$__server['relative_path'].'/polls/admin/%d/%d">rediger</a> <a href="'.$__server['relative_path'].'/polls/admin/%d/%d/delete">slett</a>]</li>',
				game::bb_to_html($row['po_text']),
				game::format_number($row['po_votes']) . " " . fword("stemme", "stemmer", $row['po_votes']),
				$poll['p_id'],
				$row['po_id'],
				$poll['p_id'],
				$row['po_id']);
		}
		
		echo '
		</ul>';
	}
	
	echo '
		<ul>
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/new">Nytt alternativ</a></li>
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/edit">Rediger avstemning</a></li>
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/forum_template">Forum mal</a></li>'.($poll['p_active'] == 0
		? '
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/active?sid='.login::$info['ses_id'].'">Sett avstemningen som aktiv</a></li>'
		: '
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/inactive?sid='.login::$info['ses_id'].'">Sett avstemningen som inaktiv</a></li>').'
			<li><a href="'.$__server['relative_path'].'/polls/admin/'.$poll['p_id'].'/delete">Slett avstemning</a></li>
		</ul>
	</div>
</div>';
	
	$_base->page->load();
}


// ny avstemning?
if (isset($pages[2]) && $pages[2] == "new")
{
	// legge til?
	if (isset($_POST['title']) && isset($_POST['text']) && verify_sid())
	{
		$title = trim(postval("title"));
		$text = trim(postval("text"));
		
		// minimum lengde = 3 tegn
		if (strlen($title) < 3)
		{
			$_base->page->add_message("Tittel må inneholde minst 3 tegn.", "error");
		}
		
		else
		{
			// legg til
			$_base->db->query("INSERT INTO polls SET p_title = ".$_base->db->quote($title).", p_text = ".$_base->db->quote($text).", p_active = 0");
			$p_id = $_base->db->insert_id();
			
			$_base->page->add_message("Avstemningen ble lagt til.");
			redirect::handle("/polls/admin/$p_id", redirect::ROOT);
		}
	}
	
	echo '
<div class="bg1_c small">
	<h1 class="bg1">Ny avstemning<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="'.$__server['relative_path'].'/polls/admin">&laquo; Tilbake</a></p>
	<div class="bg1">
		<form action="'.$__server['relative_path'].'/polls/admin/new?sid='.login::$info['ses_id'].'" method="post">
			<p><b>Tittel:</b></p>
			<p><input type="text" name="title" class="styled w200" value="'.htmlspecialchars(postval("title")).'" /></p>
			<p><b>Beskrivelse:</b></p>
			<p><textarea name="text" style="width: 90%" rows="15">'.htmlspecialchars(postval("text")).'</textarea></p>
			<p>'.show_sbutton("Legg til avstemning").' <a href="'.$__server['relative_path'].'/polls/admin">Avbryt</a></p>
		</form>
	</div>
</div>';
	
	$_base->page->load();
}


// hent alle avstemningene
$pagei = new pagei(pagei::PER_PAGE, 10, pagei::ACTIVE_GET, "side");
$result = $pagei->query("SELECT p_id, p_title, p_active, p_time_start, p_time_end, p_votes FROM polls ORDER BY p_time_end != 0, p_time_end DESC, p_id DESC");

echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Avstemninger<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="'.$__server['relative_path'].'/polls/admin/new">Ny avstemning</a></p>
	<div class="bg1">
		<p class="c">Oversikt over alle avstemninger:</p>';

if ($pagei->total == 0)
{
	echo '
		<p class="c">Ingen avstemninger er opprettet.</p>';
}

else
{
	echo '
		<table class="table center tablemb">
			<thead>
				<tr>
					<th>ID</th>
					<th>Tittel</th>
					<th>Status</th>
					<th>Start</th>
					<th>Slutt</th>
					<th>Stemmer</th>
				</tr>
			</thead>
			<tbody>';
	
	$i = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="r">'.$row['p_id'].'</td>
					<td><a href="'.$__server['relative_path'].'/polls/admin/'.$row['p_id'].'">'.htmlspecialchars($row['p_title']).'</a></td>
					<td>'.($row['p_active'] == 0 ? 'Deaktivert' : '<b>Synlig</b>').'</td>
					<td>'.($row['p_time_start'] ? $_base->date->get($row['p_time_start'])->format() : 'Ikke publisert').'</td>
					<td>'.($row['p_time_end'] ? $_base->date->get($row['p_time_end'])->format() : 'Aldri').'</td>
					<td class="r">'.game::format_number($row['p_votes']).'</td>
				</tr>';
	}
	
	echo '
			</tbody>
		</table>';
	
	if ($pagei->pages > 1)
	{
		echo '
		<p class="c">'.$pagei->pagenumbers($__server['relative_path'].'/polls/admin', $__server['relative_path'].'/polls/admin?side=_pageid_').'</p>';
	}
}

echo '
	</div>
</div>';

$_base->page->load();