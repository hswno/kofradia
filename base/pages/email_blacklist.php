<?php

class page_email_blacklist
{
	/**
	 * Construct
	 */
	public function __construct()
	{
		ess::$b->page->add_title("Blokkering av e-post");
		
		$this->handle();
		
		ess::$b->page->load();
	}
	
	/**
	 * Behandle siden
	 */
	protected function handle()
	{
		// redigere oppføring?
		if (isset($_POST['edit']))
		{
			$this->edit();
		}
		
		// slette oppføring?
		elseif (isset($_POST['delete']))
		{
			$this->delete();
		}
		
		// opprette ny oppføring?
		elseif (isset($_GET['new']))
		{
			$this->add();
		}
		
		// vis oversikt
		else
		{
			$this->index();
		}
	}
	
	/**
	 * Redigere oppføring
	 */
	protected function edit()
	{
		// ingen valg?
		if (!isset($_POST['eb_id']))
		{
			ess::$b->page->add_message("Du må velge en oppføringen.", "error");
			redirect::handle();
		}
		
		// forsøk og finn oppføringen
		$eb_id = (int) $_POST['eb_id'];
		$result = ess::$b->db->query("SELECT eb_id, eb_type, eb_value, eb_time, eb_up_id, eb_note FROM email_blacklist WHERE eb_id = $eb_id");
		$eb = mysql_fetch_assoc($result);
		
		if (!$eb)
		{
			ess::$b->page->add_message("Fant ikke oppføringen du ønsket å redigere.", "error");
			redirect::handle();
		}
		
		// lagre endringer?
		if (isset($_POST['eb_type']))
		{
			$type = $_POST['eb_type'];
			$value = trim(postval("eb_value"));
			$note = trim(postval("eb_note"));
			
			// ugyldig type?
			if ($type != "address" && $type != "domain")
			{
				ess::$b->page->add_message("Ugyldig type.", "error");
			}
			
			// ugyldig verdi for e-postadresse?
			elseif ($type == "address" && !game::validemail($value))
			{
				ess::$b->page->add_message("Verdien du skrev inn er ikke en gyldig e-postadresse.", "error");
			}
			
			// ugyldig verdi for domene?
			elseif ($type == "domain" && !preg_match("/^[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z]$/Di", $value))
			{
				ess::$b->page->add_message("Verdien du skrev inn er ikke et gyldig domenenavn eller underdomene.", "error");
			}
			
			// ingen endringer?
			elseif ($type == $eb['eb_type'] && $value == $eb['eb_value'] && $note == $eb['eb_note'])
			{
				ess::$b->page->add_message("Ingen endringer har blitt utført.", "error");
			}
			
			else
			{
				// sjekk om den allerede eksisterer
				$result = ess::$b->db->query("SELECT eb_time, eb_up_id FROM email_blacklist WHERE eb_id != $eb_id AND eb_type = ".ess::$b->db->quote($type)." AND eb_value = ".ess::$b->db->quote($value));
				$row = mysql_fetch_assoc($result);
				
				if ($row)
				{
					ess::$b->page->add_message('En identisk oppføring ble lagt til av <user id="'.$row['eb_up_id'].'" /> '.ess::$b->date->get($row['eb_time'])->format().'.', "error");
				}
				
				else
				{
					// oppdater oppføringen
					ess::$b->db->query("INSERT INTO email_blacklist SET eb_type = ".ess::$b->db->quote($type).", eb_value = ".ess::$b->db->quote($value).", eb_time = ".time().", eb_up_id = ".login::$user->player->id.", eb_note = ".ess::$b->db->quote($note));
					
					// logg
					$msg = $type != $eb['eb_type']
						? " til $value ($type)"
						: ($value != $eb['eb_value']
							? " til $value"
							: " (notat endret)");
					putlog("CREWCHAN", "E-POST BLOKKERING: ".login::$user->player->data['up_name']." endret oppføringen {$eb['eb_value']} ({$eb['eb_type']})$msg");
					
					ess::$b->page->add_message("Oppføringen ".htmlspecialchars($eb['eb_value'])." (".htmlspecialchars($eb['eb_type']).") ble oppdatert.");
					redirect::handle();
				}
			}
		}
		
		ess::$b->page->add_title("Rediger blokkering");
		ess::$b->page->add_js_domready('$("eb_value").focus();');
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Rediger blokkering av e-post<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="email_blacklist">Tilbake</a></p>
		<form action="" method="post">
			<input type="hidden" name="eb_id" value="'.$eb_id.'" />
			<input type="hidden" name="edit" />
			<dl class="dd_right">
				<dt>Type</dt>
				<dd>
					<select name="eb_type">
						<option value="address"'.(postval("eb_type", $eb['eb_type']) == "address" ? ' selected="selected"' : '').'>Spesifikk e-post</option>
						<option value="domain"'.(postval("eb_type", $eb['eb_type']) == "domain" ? ' selected="selected"' : '').'>Domenenavn</option>
					</select>
				</dd>
				<dt>Verdi</dt>
				<dd><input type="text" name="eb_value" id="eb_value" class="styled w150" value="'.htmlspecialchars(postval("eb_value", $eb['eb_value'])).'" /></dd>
				<dt>Notat</dt>
				<dd><textarea name="eb_note" rows="3" cols="30">'.htmlspecialchars(postval("eb_note", $eb['eb_note'])).'</textarea></dd>
			</dl>
			<p class="c">'.show_sbutton("Lagre endringer").'</p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Slette oppføring
	 */
	protected function delete()
	{
		// ingen valg?
		if (!isset($_POST['eb_id']))
		{
			ess::$b->page->add_message("Du må velge en oppføringen.", "error");
			redirect::handle();
		}
		
		// forsøk og finn oppføringen
		$eb_id = (int) $_POST['eb_id'];
		$result = ess::$b->db->query("SELECT eb_id, eb_type, eb_value, eb_time, eb_up_id, eb_note FROM email_blacklist WHERE eb_id = $eb_id");
		$eb = mysql_fetch_assoc($result);
		
		if (!$eb)
		{
			ess::$b->page->add_message("Fant ikke oppføringen du ønsket å slette.", "error");
			redirect::handle();
		}
		
		// bekreftet sletting?
		if (isset($_POST['confirm']))
		{
			// slett oppføringen
			ess::$b->db->query("DELETE FROM email_blacklist WHERE eb_id = $eb_id");
			
			// logg
			putlog("CREWCHAN", "E-POST BLOKKERING: ".login::$user->player->data['up_name']." slettet oppføringen {$eb['eb_value']} ({$eb['eb_type']})");
			
			ess::$b->page->add_message("Oppføringen ".htmlspecialchars($eb['eb_value'])." (".htmlspecialchars($eb['eb_type']).") ble slettet.");
			redirect::handle();
		}
		
		ess::$b->page->add_title("Slette oppføring");
		
		$type = $eb['eb_type'] == "address" ? "Spesifikk e-post" : "Domenenavn";
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Slette blokkering av e-post<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="email_blacklist">Tilbake</a></p>
		<form action="" method="post">
			<input type="hidden" name="eb_id" value="'.$eb_id.'" />
			<input type="hidden" name="delete" />
			<p>Ønsker du virkelig å slette denne oppføringen:</p>
			<dl class="dd_right">
				<dt>Type</dt>
				<dd>'.$type.'</dd>
				<dt>Verdi</dt>
				<dd><b>'.htmlspecialchars($eb['eb_value']).'</b></dd>
				<dt>Opprettet</dt>
				<dd>'.ess::$b->date->get($eb['eb_time'])->format().'</dd>
				<dt>Opprettet av</dt>
				<dd><user id="'.$eb['eb_up_id'].'" /></dd>
				<dt>Notat</dt>
				<dd>'.game::format_data($eb['eb_note'], "bb-opt", "<i>Ingen</i>").'</dd>
			</dl>
			<p class="c red">'.show_sbutton("Bekreft sletting", 'name="confirm"').'</p>
		</form>
		<p class="c"><a href="email_blacklist">Tilbake</a></p>
	</div>
</div>';
	}
	
	/**
	 * Opprette ny oppføring
	 */
	protected function add()
	{
		// opprette?
		if (isset($_POST['eb_type']))
		{
			$type = $_POST['eb_type'];
			$value = trim(postval("eb_value"));
			$note = trim(postval("eb_note"));
			
			// ugyldig type?
			if ($type != "address" && $type != "domain")
			{
				ess::$b->page->add_message("Ugyldig type.", "error");
			}
			
			// ugyldig verdi for e-postadresse?
			elseif ($type == "address" && !game::validemail($value))
			{
				ess::$b->page->add_message("Verdien du skrev inn er ikke en gyldig e-postadresse.", "error");
			}
			
			// ugyldig verdi for domene?
			elseif ($type == "domain" && !preg_match("/^[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z]$/Di", $value))
			{
				ess::$b->page->add_message("Verdien du skrev inn er ikke et gyldig domenenavn eller underdomene.", "error");
			}
			
			else
			{
				// sjekk om den allerede eksisterer
				$result = ess::$b->db->query("SELECT eb_time, eb_up_id FROM email_blacklist WHERE eb_type = ".ess::$b->db->quote($type)." AND eb_value = ".ess::$b->db->quote($value));
				$row = mysql_fetch_assoc($result);
				
				if ($row)
				{
					ess::$b->page->add_message('En identisk oppføring ble lagt til av <user id="'.$row['eb_up_id'].'" /> '.ess::$b->date->get($row['eb_time'])->format().'.', "error");
				}
				
				else
				{
					// opprett oppføringen
					ess::$b->db->query("INSERT INTO email_blacklist SET eb_type = ".ess::$b->db->quote($type).", eb_value = ".ess::$b->db->quote($value).", eb_time = ".time().", eb_up_id = ".login::$user->player->id.", eb_note = ".ess::$b->db->quote($note));
					
					// logg
					putlog("CREWCHAN", "E-POST BLOKKERING: ".login::$user->player->data['up_name']." la til oppføringen $value ($type)");
					
					ess::$b->page->add_message("Oppføringen ble lagt til.");
					redirect::handle();
				}
			}
		}
		
		ess::$b->page->add_title("Ny blokkering");
		ess::$b->page->add_js_domready('$("eb_value").focus();');
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Ny blokkering av e-post<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="email_blacklist">Tilbake</a></p>
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Type</dt>
				<dd>
					<select name="eb_type">
						<option value="address"'.(postval("eb_type") == "address" ? ' selected="selected"' : '').'>Spesifikk e-post</option>
						<option value="domain"'.(postval("eb_type") == "domain" || !isset($_POST['eb_type']) ? ' selected="selected"' : '').'>Domenenavn</option>
					</select>
				</dd>
				<dt>Verdi</dt>
				<dd><input type="text" name="eb_value" id="eb_value" class="styled w150" value="'.htmlspecialchars(postval("eb_value")).'" /></dd>
				<dt>Notat</dt>
				<dd><textarea name="eb_note" rows="3" cols="30">'.htmlspecialchars(postval("eb_note")).'</textarea></dd>
			</dl>
			<p class="c">'.show_sbutton("Legg til").'</p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Vis oversikt
	 */
	protected function index()
	{
		// hent data som er blokkert
		$result = ess::$b->db->query("SELECT eb_id, eb_type, eb_value, eb_time, eb_up_id, eb_note FROM email_blacklist ORDER BY eb_value");
		$data = array(
			"address" => array(),
			"domain" => array()
		);
		while ($row = mysql_fetch_assoc($result))
		{
			$data[$row['eb_type']][] = $row;
		}
		
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Blokkering av e-post<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p class="c"><a href="email_blacklist?new">Opprett ny blokkering &raquo;</a></p>
		<p>Spesifikke e-postadresser som er blokkert:</p>';
		
		if (count($data['address']) == 0)
		{
			echo '
		<p class="c">Ingen spesifikke e-postadresser er blokkert.</p>';
		}
		
		else
		{
			echo '
		<form action="" method="post">
			<table class="table center">
				<thead>
					<tr>
						<th>E-postadresse</th>
						<th>Lagt til</th>
						<th>Av</th>
						<th>Notat</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			foreach ($data['address'] as $row)
			{
				$note = game::format_data($row['eb_note']);
				if (empty($note)) $note = '&nbsp;';
				
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><a href="brukere/finn?email='.urlencode($row['eb_value']).'"><input type="radio" name="eb_id" value="'.$row['eb_id'].'" />'.htmlspecialchars($row['eb_value']).'</a></td>
						<td class="r">'.ess::$b->date->get($row['eb_time'])->format().'</td>
						<td><user id="'.$row['eb_up_id'].'" /></td>
						<td>'.$note.'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Rediger", 'name="edit"').' <span class="red">'.show_sbutton("Slett", 'name="delete"').'</span></p>
		</form>';
		}
		
		echo '
		<p>E-postdomener som er blokkert:</p>';
		
		if (count($data['domain']) == 0)
		{
			echo '
		<p class="c">Ingen domener er blokkert.</p>';
		}
		
		else
		{
			echo '
		<form action="" method="post">
			<table class="table center">
				<thead>
					<tr>
						<th>Domene</th>
						<th>Lagt til</th>
						<th>Av</th>
						<th>Notat</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			foreach ($data['domain'] as $row)
			{
				$note = game::format_data($row['eb_note']);
				if (empty($note)) $note = '&nbsp;';
				
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><a href="brukere/finn?email='.urlencode('*@'.$row['eb_value']).'"><input type="radio" name="eb_id" value="'.$row['eb_id'].'" />'.htmlspecialchars($row['eb_value']).'</a></td>
						<td class="r">'.ess::$b->date->get($row['eb_time'])->format().'</td>
						<td><user id="'.$row['eb_up_id'].'" /></td>
						<td>'.$note.'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Rediger", 'name="edit"').' <span class="red">'.show_sbutton("Slett", 'name="delete"').'</span></p>
		</form>';
		}
		
		echo '
	</div>
</div>';
	}
}