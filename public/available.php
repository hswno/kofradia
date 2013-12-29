<?php

/**
 * TODO: Slette avstemning
 * TODO: Rediger alternativene i en avstemning
 * -- Legge til
 * -- Slette
 * -- Flytte opp
 * -- Flytte ned
 * -- Redigere tittel og beskrivelse
 * TODO: Slette egen stemme
 */

require "base.php";
global $_base;

access::need("crewet");
$_base->page->add_title("Avstemninger");
$_base->page->theme_file = "doc";

class available
{
	/**
	 * Hent en avstemning
	 * @return available_group
	 */
	public static function get_ag($ag_id)
	{
		$group = new available_group($ag_id);
		
		// fant ikke?
		if (!$group->info)
		{
			return false;
		}
		
		return $group;
	}
	
	/**
	 * Legg til ny avstemning
	 */
	public static function add_ag($title, $description)
	{
		global $_base;
		
		$title = trim($title);
		$description = trim($description);
		
		// kontroller tittel
		if (mb_strlen($title) < 3)
		{
			return 'Tittelen må inneholde minimum 3 tegn.';
		}
		
		// legg til
		\Kofradia\DB::get()->exec("INSERT INTO available_group SET ag_title = ".\Kofradia\DB::quote($title).", ag_description = ".\Kofradia\DB::quote($description).", ag_up_id = ".login::$user->player->id.", ag_time = ".time());
		
		// returner nytt objekt
		return self::get_ag(\Kofradia\DB::get()->lastInsertId());
	}
}

class available_group
{
	/** Avstemning ID-en */
	public $ag_id = false;
	
	/** Info */
	public $info = false;
	
	/** Oversikt over valgene */
	public $ai = false;
	
	/** Data for valgene */
	public $av_data = false;
	
	/**
	 * Constructor: Hent item
	 */
	public function __construct($ag_id)
	{
		global $_base;
		
		$this->ag_id = (int) $ag_id;
		$result = \Kofradia\DB::get()->query("SELECT ag_id, ag_title, ag_description, ag_up_id FROM available_group WHERE ag_id = $this->ag_id");
		
		// fant ikke?
		if ($result->rowCount() == 0)
		{
			return;
		}
		
		$this->info = $result->fetch();
	}
	
	/**
	 * Rediger tittel og beskrivelse
	 *
	 * @param string $title
	 * @param string $description
	 * @return string failure OR true
	 */
	public function edit($title, $description)
	{
		global $_base;
		
		$title = trim($title);
		$description = trim($description);
		
		// kontroller tittel
		if (mb_strlen($title) < 3)
		{
			return 'Tittelen må inneholde minimum 3 tegn.';
		}
		
		// oppdater
		\Kofradia\DB::get()->exec("UPDATE available_group SET ag_title = ".\Kofradia\DB::quote($title).", ag_description = ".\Kofradia\DB::quote($description)." WHERE ag_id = $this->ag_id");
		
		return true;
	}
	
	/**
	 * Hent valgene
	 */
	public function get_ai_list()
	{
		global $_base;
		
		// hent enhetene
		$result = \Kofradia\DB::get()->query("SELECT ai_id, ai_title, ai_description, ai_order FROM available_items WHERE ai_ag_id = $this->ag_id ORDER BY ai_order");
		
		$this->ai = array();
		while ($row = $result->fetch())
		{
			$this->ai[$row['ai_id']] = $row;
		}
	}
	
	/**
	 * Lagre valg
	 * @param array $ai_list (ai_id, note, state)
	 * @param string $note
	 */
	public function vote($ai_list, $note)
	{
		global $_base;
		
		// hent alle valgene
		if (!$this->ai) $this->get_ai_list();
		
		// tøm evt. liste
		\Kofradia\DB::get()->exec("DELETE FROM available_votes WHERE av_ag_id = $this->ag_id AND av_up_id = ".login::$user->player->id);
		
		// legg til i brukeroversikten
		\Kofradia\DB::get()->exec("REPLACE INTO available_users SET au_ag_id = $this->ag_id, au_up_id = ".login::$user->player->id.", au_note = ".\Kofradia\DB::quote($note).", au_time = ".time());
		
		// gå gjennom hvert valg og legg til hvis det finnes
		$count = 0;
		foreach ($ai_list as $ai)
		{
			$ai_id = (int) $ai[0];
			if (!isset($this->ai[$ai_id])) continue;
			
			// status
			$state = $ai[2] ? 1 : 0;
			if (!$state && empty($ai[1])) continue; // hopp over fordi den verken er valgt eller har notat
			
			$a = \Kofradia\DB::get()->exec("INSERT IGNORE INTO available_votes SET av_ag_id = $this->ag_id, av_ai_id = $ai_id, av_up_id = ".login::$user->player->id.", av_state = $state, av_note = ".\Kofradia\DB::quote($ai[1]));
			if ($a > 0) $count++;
		}
		
		return $count;
	}
	
	/**
	 * Hent data for avstemningene
	 */
	public function get_av_data()
	{
		global $_base;
		
		// hent brukerinfo
		$result = \Kofradia\DB::get()->query("SELECT au_up_id, au_note, au_time, up_name, up_access_level FROM available_users LEFT JOIN users_players ON au_up_id = up_id WHERE au_ag_id = $this->ag_id ORDER BY up_name");
		$data = array();
		while ($row = $result->fetch())
		{
			$row['votes'] = array();
			$data[$row['au_up_id']] = $row;
		}
		
		// hent data
		$result = \Kofradia\DB::get()->query("SELECT av_ai_id, av_up_id, av_state, av_note FROM available_votes WHERE av_ag_id = $this->ag_id");
		while ($row = $result->fetch())
		{
			if (!isset($data[$row['av_up_id']])) continue;
			$data[$row['av_up_id']]['votes'][$row['av_ai_id']] = $row;
		}
		
		return $data;
	}
}

// har vi valgt en avstemning?
if (isset($_GET['ag_id']))
{
	// hent info
	$ag = available::get_ag($_GET['ag_id']);
	if (!$ag)
	{
		$_base->page->add_message("Fant ikke avstemningen.", "error");
		redirect::handle();
	}
	
	$_base->page->add_title($ag->info['ag_title']);
	redirect::store(PHP_SELF."?ag_id=$ag->ag_id", redirect::SERVER);
	
	// hent alternativene
	$ag->get_ai_list();
	
	// legge inn stemme?
	if (isset($_POST['note']) && (!isset($_POST['ai']) || is_array($_POST['ai'])))
	{
		// _POST: note, ai[<id>] ai_note[<id>]
		$note = trim(postval("note"));
		$ai_list = array();
		
		// gå gjennom alle alternativene og sjekk om det er valgt eller om et notat er lagt med
		foreach ($ag->ai as $ai)
		{
			$set = isset($_POST['ai'][$ai['ai_id']]);
			$ai_note = isset($_POST['ai_note'][$ai['ai_id']]) ? trim($_POST['ai_note'][$ai['ai_id']]) : '';
			
			// enten valgt eller har notat
			if ($set || $ai_note != '')
			{
				$ai_list[] = array($ai['ai_id'], $ai_note, $set);
			}
		}
		
		// legg til
		$ag->vote($ai_list, $note);
		
		$_base->page->add_message("Valgene ble registrert.");
		redirect::handle();
	}
	
	// redigere informasjon?
	elseif (isset($_GET['edit']))
	{
		// ikke tilgang?
		if ($ag->info['ag_up_id'] != login::$user->player->id && !access::has("admin"))
		{
			$_base->page->add_message("Du har ikke tilgang til å redigere denne avstemningen.", "error");
			redirect::handle();
		}
		
		// lagre endringer
		if (isset($_POST['title']))
		{
			$ret = $ag->edit(postval("title"), postval("description"));
			if (is_string($ret))
			{
				$_base->page->add_message($ret, "error");
			}
			
			else
			{
				$_base->page->add_message("Informasjonen ble oppdatert.");
				redirect::handle();
			}
		}
		
		echo '
<h1>Rediger avstemning</h1>
<form action="" method="post">
	<dl class="dl_100px">
		<dt>Tittel</dt>
		<dd><input type="text" name="title" class="styled w400" value="'.htmlspecialchars(postval("title", $ag->info['ag_title'])).'" /></dd>
		<dt>Beskrivelse</dt>
		<dd><textarea name="description" rows="10" cols="60" class="w400">'.htmlspecialchars(postval("description", $ag->info['ag_description'])).'</textarea></dd>
		<dt>&nbsp;</dt>
		<dd>'.show_sbutton("Oppdater informasjon").' <a href="?ag_id='.$ag->ag_id.'">Avbryt</a></dd>
	</dl>
</form>';
		
		$_base->page->load();
	}
	
	// redigere informasjon?
	elseif (isset($_GET['edit']))
	{
		// ikke tilgang?
		if ($ag->info['ag_up_id'] != login::$user->player->id && !access::has("admin"))
		{
			$_base->page->add_message("Du har ikke tilgang til å redigere denne avstemningen.", "error");
			redirect::handle();
		}
	}
	
	// hent alle svarene
	$data = $ag->get_av_data();
	
	echo '
<h1>'.htmlspecialchars($ag->info['ag_title']).'</h1>';
	
	// knapp til redigering
	if ($ag->info['ag_up_id'] == login::$user->player->id || access::has("admin"))
	{
		echo '
<p class="h_right"><a href="?ag_id='.$ag->ag_id.'&amp;edit" class="button">Rediger informasjon</a> <a href="?ag_id='.$ag->ag_id.'&amp;edit_ai" class="button">Rediger alternativer</a></p>';
	}
	
	// beskrivelse?
	$description = game::format_data($ag->info['ag_description']);
	if (!empty($description))
	{
		echo '
<div class="p">'.$description.'</div>';
	}
	
	// vis valgene og stemmene som er gitt
	if (count($ag->ai) == 0)
	{
		echo '
<p>Ingen alternativer er opprettet.</p>';
	}
	
	else
	{
		echo '
<form action="" method="post">
	<table class="table" style="width: 100%">
		<thead>
			<tr>
				<th>Bruker</th>
				<th>Tidspunkt</th>
				<th>Kommentar</th>';
		
		foreach ($ag->ai as $row)
		{
			echo '
				<th title="'.htmlspecialchars($row['ai_description']).'">'.htmlspecialchars($row['ai_title']).'</th>';
		}
		
		echo '
			</tr>
		</thead>
		<tbody>';
		
		$i = 0;
		$found = false;
		if (count($data) == 0)
		{
			echo '
			<tr>
				<td colspan="'.(count($ag->ai)+3).'">Ingen stemmer er gitt.</td>
			</tr>';
		}
		
		else
		{
			foreach ($data as $row)
			{
				if ($row['au_up_id'] == login::$user->player->id) $found = $row;
				$bb = game::bb_to_html($row['au_note']);
				
				echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td>'.game::profile_link($row['au_up_id'], $row['up_name'], $row['up_access_level']).'</td>
				<td class="nowrap">'.$_base->date->get($row['au_time'])->format().'</td>
				<td class="ai_note">'.(empty($bb) ? '&nbsp;' : $bb).'</td>';
				
				foreach ($ag->ai as $ai)
				{
					$av = isset($row['votes'][$ai['ai_id']]) ? $row['votes'][$ai['ai_id']] : false;
					$set = $av ? $av['av_state'] != 0 : false;
					$note = $av ? game::bb_to_html($av['av_note']) : '';
					
					echo '
				<td'.($set ? ' class="av_true"' : ' class="av_false"').'>'.(empty($note) ? '&nbsp;' : $note).'</td>';
				}
				
				echo '
			</tr>';
			}
		}
		
		// legge til/endre stemme
		echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td colspan="2">Din stemme</td>
				<td><textarea name="note" rows="6" cols="15">'.htmlspecialchars(postval("note", $found ? $found['au_note'] : '')).'</textarea></td>';
		
		foreach ($ag->ai as $ai)
		{
			$av = $found && isset($found['votes'][$ai['ai_id']]) ? $found['votes'][$ai['ai_id']] : false;
			$set = $av ? $av['av_state'] != 0 : false;
			$note = $av ? $av['av_note'] : '';
			
			echo '
				<td class="ai_option">
					<input type="checkbox" name="ai['.$ai['ai_id'].']"'.($set ? ' checked="checked"' : '').' /> Valgt<br />
					Kommentar:<br />
					<textarea name="ai_note['.$ai['ai_id'].']" rows="3" cols="15">'.htmlspecialchars(isset($_POST['ai_note'][$ai['ai_id']]) ? $_POST['ai_note'][$ai['ai_id']] : $note).'</textarea>
				</td>';
		}
		
		echo '
			</tr>
		</tbody>
	</table>
	<p>'.show_sbutton($found ? "Oppdater stemme" : "Registrer stemme").'</p>
</form>';
		
		$_base->page->add_js_domready('
	$$(".ai_option").each(function(elm)
	{
		var box = elm.getElement("input");
		//var info = new Element("span").inject(elm);
		//elm.setStyle("cursor", "pointer");
		//box.setStyle("display", "none");
		elm.addEvent("click", function()
		{
			box.set("checked", !box.get("checked"));
		});
		box.addEvent("click", function()
		{
			box.set("checked", !box.get("checked"));
		});
		elm.getElement("textarea").addEvent("click", function(event)
		{
			event.stop();
		});
		/*if (box.get("checked"))
		{
			info.set("text", "OK");
		}*/
	});');
		$_base->page->add_css('
td.ai_note { font-size: 10px }
td.av_true { background-color: #99FF99 !important; font-size: 10px }
td.av_false { background-color: #FF9999 !important; font-size: 10px }');
	}
	
	$_base->page->load();
}

// opprette ny avstemning
elseif (isset($_GET['new']))
{
	if (!access::has("sadmin"))
	{
		$_base->page->add_message("Denne muligheten er ikke klar enda. Må legges til manuelt i databasen.", "error");
		redirect::handle();
	}
	
	// opprette?
	if (isset($_POST['title']))
	{
		$title = postval("title");
		$description = postval("description");
		
		// prøv å legg til
		$ret = available::add_ag($title, $description);
		if (is_string($ret))
		{
			$_base->page->add_message($ret, "error");
		}
		
		else
		{
			$_base->page->add_message("Avstemningen ble opprettet.");
			redirect::handle(PHP_SELF."?ag_id={$ret->ag_id}", redirect::SERVER);
		}
	}
	
	echo '
<div class="bg1_c small">
	<h1 class="bg1">Opprett ny avstemning<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Tittel</dt>
				<dd><input type="text" name="title" class="styled w300" value="'.htmlspecialchars(postval("title")).'" /></dd>
				<dt>Beskrivelse</dt>
				<dd><textarea name="description" rows="10" cols="30" class="w300">'.htmlspecialchars(postval("description")).'</textarea></dd>
			</dl>
			<p class="c">'.show_sbutton("Legg til").' <a href="'.PHP_SELF.'">Avbryt</a></p>
		</form>
	</div>
</div>';
	
	$_base->page->load();
}

// hent alle
$pagei = new pagei(pagei::ACTIVE_GET, "side");
$result = $pagei->query("SELECT ag_id, ag_title, ag_description, ag_up_id, ag_time FROM available_group ORDER BY ag_time DESC");

echo '
<div class="bg1_c small">
	<h1 class="bg1">Avstemninger<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';

if ($result->rowCount() == 0)
{
	echo '
		<p>Ingen avstemninger er opprettet.</p>';
}

else
{
	echo '
		<table class="table tablemt" style="width: 100%">
			<thead>
				<tr>
					<th>Tittel</th>
					<th>Beskrivelse</th>
					<th>Opprettet</th>
				</tr>
			</thead>
			<tbody>';
	
	$i = 0;
	while ($row = $result->fetch())
	{
		$bb = game::bb_to_html($row['ag_description']);
		
		echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><a href="?ag_id='.$row['ag_id'].'">'.htmlspecialchars($row['ag_title']).'</a></td>
					<td>'.(empty($bb) ? '&nbsp;' : $bb).'</td>
					<td>'.$_base->date->get($row['ag_time'])->format().'<br />Av <user id="'.$row['ag_up_id'].'" /></td>
				</tr>';
	}
	
	echo '
			</tbody>
		</table>';
}

echo '
		<p><a href="?new">Opprett ny avstemning &raquo;</a></p>
	</div>
</div>';

$_base->page->load();