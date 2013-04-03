<?php

define("ALLOW_GUEST", true);
define("FORCE_HTTPS", true);

require "base.php";
global $_base;

$_base->page->add_title("Søknader");

// hent aktuell søknad
$id = $_base->db->quote(getval("so_id"));
$result = $_base->db->query("SELECT so_id, so_title, so_info, so_expire, so_status FROM soknader_oversikt WHERE so_id = $id");

$soknad = mysql_fetch_assoc($result);
$closed = $soknad['so_expire'] <= time();

// finnes ikke, eller ikke tilgang til ikke publisert søknad
if (!$soknad || ($soknad['so_status'] == 0 && !access::has("crewet")))
{
	$_base->page->add_message("Fant ikke søknaden.", "error");
	redirect::handle("soknader");
}
$_base->page->add_title($soknad['so_title']);
redirect::store("soknader_vis?so_id={$soknad['so_id']}");

// hent felt til søknaden
$result = $_base->db->query("SELECT sf_id, sf_title, sf_extra, sf_default_value, sf_params FROM soknader_felt WHERE sf_so_id = {$soknad['so_id']} ORDER BY sf_sort");
$felt = array();
while ($row = mysql_fetch_assoc($result))
{
	$row['params'] = new params($row['sf_params']);
	$felt[$row['sf_id']] = $row;
}


// hent informasjon om brukeren og denne søknaden
$applicant = null;
if (login::$logged_in)
{
	$result = $_base->db->query("SELECT sa_id, sa_added, sa_status, sa_updated FROM soknader_applicants WHERE sa_so_id = {$soknad['so_id']} AND sa_up_id = ".login::$user->player->id);
	$applicant = mysql_fetch_assoc($result);
}

// hent felt som denne brukeren har lagt til data for
if ($applicant)
{
	$result = $_base->db->query("SELECT saf_sf_id, saf_value FROM soknader_applicants_felt WHERE saf_sa_id = {$applicant['sa_id']}");
	$applicant_felt = array();
	
	while ($row = mysql_fetch_assoc($result))
	{
		$applicant_felt[$row['saf_sf_id']] = $row['saf_value'];
	}
}



// vis informasjon om søknaden
echo '
<div class="bg1_c small">
	<h1 class="bg1">'.htmlspecialchars($soknad['so_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="soknader">&laquo; Tilbake</a></p>
	<div class="bg1">';

// informasjon
if (!empty($soknad['so_info']))
{
	echo '
		<div class="p">'.game::format_data($soknad['so_info']).'</div>';
}

echo '
		<p><u>Søknadsfrist</u><br />'.$_base->date->get($soknad['so_expire'])->format(date::FORMAT_SEC).'</p>
		
		<h2 class="bg1" style="margin-top: 25px">Søknad<span class="left2"></span><span class="right2"></span></h2>
		<div class="bg1">';

// ikke logget inn?
if (!login::$logged_in)
{
	echo '
			<p class="c">Du må <a href="&rpath;/?orign='.urlencode($_SERVER['REQUEST_URI']).'">logge inn</a> for å levere søknad.</p>';
}

// ingen søknad levert?
elseif ($closed && (!$applicant || $applicant['sa_status'] == 0))
{
	echo '
			<p class="c">Ingen søknad ble levert før tidsfristen.</p>';
}

elseif ($applicant)
{
	// trekk tilbake søknad
	if (isset($_POST['trekk_tilbake']) && !$closed && $applicant['sa_status'] == 1)
	{
		$_base->db->query("UPDATE soknader_applicants SET sa_status = 0 WHERE sa_id = {$applicant['sa_id']}");
		$_base->page->add_message("Du har trukket tilbake søknaden.");
		redirect::handle();
	}
	
	// vise levert søknad?
	if ($applicant['sa_status'] == 1)
	{
		echo '
			<p class="c">Du leverte din søknad '.$_base->date->get($applicant['sa_updated'])->format().'.</p>';
		
		foreach ($felt as $key => $info)
		{
			$value = isset($applicant_felt[$key]) ? $applicant_felt[$key] : $info['sf_default_value'];
			$bb = false;
			
			switch ($info['params']->get("type"))
			{
				case "text":
					$bb = true;
					break;
			}
			
			$value = trim($value);
			if ($value == "")
			{
				echo '
			<p>
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				<i>Mangler verdi.</i>
			</p>';
			}
			
			else
			{
				echo '
			<div class="p">
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				'.($bb ? game::format_data($value) : htmlspecialchars($value)).(($post = $info['params']->get("post")) != "" ? ' '.$post : '').'
			</div>';
			}
		}
		
		// kan trekkes tilbake?
		if (!$closed)
		{
			echo '
			<form action="" method="post">
				<p class="c">'.show_sbutton("Trekk tilbake søknaden", 'name="trekk_tilbake"').'</p>
				<p class="c"><i>Dersom du trekker tilbake søknaden vil du kunne redigere og sende inn søknaden igjen innen fristen.</i></p>
			</form>';
		}
		
		else
		{
			echo '
			<p class="c">Fristen har gått ut. Du kan ikke trekke tilbake din søknad.</p>';
		}
	}
	
	// rediger søknad
	else
	{
		$preview = false;
		$errors = array();
		
		// lagre endringer
		if (isset($_POST['lagre']) || isset($_POST['preview']) || isset($_POST['send_inn']))
		{
			// kontroller data
			$fields = array();
			foreach ($felt as $key => $info)
			{
				$value = isset($_POST['sf_'.$key]) ? $_POST['sf_'.$key] : NULL;
				
				if ($value === NULL || $value == "")
				{
					// må fylles ut?
					if (!$info['params']->get("optional"))
					{
						$errors[$key] = ' <span style="color: #FF6666">[mangler innhold]</span>';
					}
				}
				
				else
				{
					// kontroller typen
					switch ($info['params']->get("type"))
					{
						case "varchar":
							// kontroller lengde
							$maxlength = intval($info['params']->get("maxlength", 255));
							if (mb_strlen($value) > $maxlength) $value = mb_substr($value, 0, $maxlength);
							break;
						
						case "number":
							// kontroller tall og størrelse
							$value = game::intval($value);
							$max_value = $info['params']->get("number_max", 999999999);
							if ($value > $max_value) $value = $max_value;
							break;
						
						case "text":
							// ingen endringer
							break;
					}
					
					$fields[$key] = $value;
				}
			}
			
			// slett gamle felt
			$_base->db->begin();
			$_base->db->query("DELETE FROM soknader_applicants_felt WHERE saf_sa_id = {$applicant['sa_id']}");
			$_base->db->query("UPDATE soknader_applicants SET sa_updated = ".time()." WHERE sa_id = {$applicant['sa_id']}");
			
			// legg til nye felt
			foreach ($fields as $key => $value)
			{
				$_base->db->query("INSERT INTO soknader_applicants_felt SET saf_sa_id = {$applicant['sa_id']}, saf_sf_id = $key, saf_value = ".$_base->db->quote($value));
			}
			
			
			// kun lagre?
			if (isset($_POST['lagre']))
			{
				$_base->page->add_message("Endringene ble lagret.");
				$_base->db->commit();
				redirect::handle();
			}
			
			// forhåndsvise
			elseif (isset($_POST['preview']))
			{
				$_base->page->add_message("Endringene ble lagret.");
				$_base->db->commit();
				redirect::handle("soknader_vis?so_id={$soknad['so_id']}&preview");
			}
			
			// sende inn
			else
			{
				// feil?
				if (count($errors) > 0)
				{
					$_base->page->add_message("Kan ikke sende inn søknad fordi noen felt ikke er korrekt utfylt. Se feltene. Endringene ble lagret.", "error");
					$_base->db->commit();
				}
				
				else
				{
					// send inn søknaden
					$_base->db->query("UPDATE soknader_applicants SET sa_status = 1 WHERE sa_id = {$applicant['sa_id']}");
					$_base->db->commit();
					
					$_base->page->add_message("Søknaden er nå sendt inn. Hvis du vil gjøre endringer kan du trekke tilbake søknaden innen fristen har gått ut.");
					redirect::handle();
				} 
			}
		}
		
		// slett søknad
		if (isset($_POST['slett']) && isset($_POST['confirm']))
		{
			$_base->db->query("DELETE FROM soknader_applicants_felt WHERE saf_sa_id = {$applicant['sa_id']}");
			$_base->db->query("DELETE FROM soknader_applicants WHERE sa_id = {$applicant['sa_id']}");
			
			$_base->page->add_message("Søknaden ble slettet.");
			redirect::handle();
		}
		
		// slette?
		if (isset($_POST['slett'])) $preview = true;
		
		// forhåndsvise?
		if ($preview || isset($_GET['preview']))
		{
			echo '
			<div class="warning c" id="scroll_here">
				<p><b>Denne søknaden er <u>ikke</u> sendt inn enda.</b></p>
				<p>Husk å send inn søknaden før fristen.</p>
			</div>';
			
			echo '
			<boxes />';
			
			// slette søknad?
			if (isset($_POST['slett']))
			{
				echo '
			<div class="warning c">
				<form action="" method="post">
					<input type="hidden" name="slett" />
					<p>Er du sikker på at du vil slette denne søknaden?</p>
					<p>'.show_sbutton("Slett", 'name="confirm"').' <a href="soknader_vis?so_id='.$soknad['so_id'].'">Avbryt</a></p>
				</form>
			</div>';
			}
			
			// vis feltene
			echo '
			<p><b>Forhåndsvisning:</b></p>';
			
			foreach ($felt as $key => $info)
			{
				$value = isset($applicant_felt[$key]) ? $applicant_felt[$key] : $info['sf_default_value'];
				$bb = false;
				
				switch ($info['params']->get("type"))
				{
					case "text":
						$bb = true;
						break;
				}
				
				$value = trim($value);
				if ($value == "")
				{
					echo '
			<p>
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				<i>Mangler verdi.</i>
			</p>';
				}
				
				else
				{
					echo '
			<div class="p">
				<u>'.htmlspecialchars($info['sf_title']).'</u><br />
				'.($bb ? game::format_data($value) : htmlspecialchars($value)).(($post = $info['params']->get("post")) != "" ? ' '.$post : '').'
			</div>';
				}
			}
			
			echo '
			<p><a href="soknader_vis?so_id='.$soknad['so_id'].'">&laquo; Tilbake</a></p>';
		}
		
		else
		{
			echo '
		<form action="" method="post">
			<div class="warning c">
				<p><b>Denne søknaden er <u>ikke</u> sendt inn enda.</b></p>
				<p>Husk å send inn søknaden før fristen.</p>
			</div>';
			
			$_base->page->add_css('.sf_p { margin-bottom: 5px } .sf { margin-top: 0 }');
			
			// vis feltene
			foreach ($felt as $key => $info)
			{
				$field = 'Ukjent felt';
				$value = isset($applicant_felt[$key]) ? $applicant_felt[$key] : $info['sf_default_value'];
				$dl = true;
				
				switch ($info['params']->get("type"))
				{
					case "varchar":
					$maxlength = intval($info['params']->get("maxlength", 255));
					$class = "styled w200";
					if ($maxlength <= 5) $class = "styled w40";
					elseif ($maxlength <= 10) $class = "styled w80";
					#elseif ($maxlength <= 20) $class = "styled w150";
					elseif ($maxlength <= 30) $class = "styled w150";
					elseif ($maxlength <= 40) $class = "styled w200";
					elseif ($maxlength <= 70) $class = "styled w250";
					$field = '<input type="text" name="sf_'.$key.'" value="'.htmlspecialchars($value).'" maxlength="'.$maxlength.'" class="'.$class.'" />';
					break;
					
					case "number":
					$maxlength = mb_strlen($info['params']->get("number_max", 999999999));
					$class = "styled w100";
					if ($maxlength < 5) $class = "styled w40";
					elseif ($maxlength < 10) $class = "styled w80";
					$field = '<input type="text" name="sf_'.$key.'" value="'.htmlspecialchars($value).'" maxlength="'.$maxlength.'" class="'.$class.'" />';
					break;
					
					case "text":
					$rows = intval($info['params']->get("textarea_rows", 5));
					$field = '<textarea name="sf_'.$key.'" rows="'.$rows.'" cols="60" style="width: 344px">'.htmlspecialchars($value).'</textarea>';
					$dl = false;
					break;
				}
				
				if ($dl)
				{
					echo '
			<dl class="dd_right dl_2x">
				<dt>'.htmlspecialchars($info['sf_title']).(isset($errors[$key]) ? $errors[$key] : '').''.(!empty($info['sf_extra']) ? ' '.game::format_data($info['sf_extra']) : '').'</dt>
				<dd>'.$field.(($post = $info['params']->get("post")) != "" ? ' '.$post : '').'</dd>
			</dl>';
				}
				
				else
				{
					echo '
			<p class="sf_p">'.htmlspecialchars($info['sf_title']).(isset($errors[$key]) ? $errors[$key] : '').''.(!empty($info['sf_extra']) ? '<br />'.game::format_data($info['sf_extra']) : '').'</p>
			<p class="sf">'.$field.'</p>';
				}
			}
			
			echo '
			<p>
				'.show_sbutton("Lagre", 'name="lagre"').'
				'.show_sbutton("Lagre og forhåndsvis", 'name="preview"').'
				'.show_sbutton("Lagre og send inn", 'name="send_inn"').'
				'.show_sbutton("Slett", 'name="slett"').'
			</p>
			<p><i>Du kan trekke tilbake søknaden etter du har sendt den inn for å gjøre endringer innen fristen. Etter fristen vil du ikke kunne sende inn eller trekke tilbake søknaden.</i></p>
		</form>';
		}
	}
}

elseif (isset($_POST['opprett']) && login::$logged_in)
{
	// opprett søknad
	$_base->db->query("INSERT INTO soknader_applicants SET sa_so_id = {$soknad['so_id']}, sa_up_id = ".login::$user->player->id.", sa_added = ".time().", sa_status = 0");
	$_base->page->add_message("Du har nå opprettet en søknad og kan redigere denne.");
	redirect::handle();
}

else
{
	echo '
			<p class="c">Du har ikke opprettet noen søknad.</p>
			<form action="" method="post">
				<p class="c">'.show_sbutton("Opprett søknad", 'name="opprett"').'</p>
			</form>';
}

echo '
		</div>
	</div>
</div>';

$_base->page->load();