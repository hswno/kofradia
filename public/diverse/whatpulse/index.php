<?php

require "../../base.php";
global $_whatpulse, $_base;

// sørg for at whatpulse-innstillingene lastes inn
class_exists("whatpulse"); // brukes kun for autoloader

access::no_guest();
$_base->page->add_title("WhatPulse");

$wpFelt = $_whatpulse['fields_text'];

$player = login::$user->player;

// skal vi laste inn siden for noen andre spillere?
if (isset($_GET['up_id']) && access::has("mod"))
{
	$player = player::get(intval(getval('up_id')));
	if (!$player OR $player->id == login::$user->player->id) redirect::handle();
	
	ess::$b->page->add_message('Du viser nå WhatPulse-informasjonen til <user id="'.$player->id.'" />');
	redirect::store("?up_id={$player->id}");
}

// sjekk om vi har WhatPulse registrert
$result = \Kofradia\DB::get()->query("SELECT sw_userid, sw_time_update, sw_xml, sw_params FROM stats_whatpulse WHERE sw_up_id = ".$player->id." FOR UPDATE");

if ($result->rowCount() == 0)
{
	echo '
<h1>WhatPulse</h1>
<div style="width: 300px" class="center">
	<div class="section">
		<h3>Informasjon</h3>
		<p>
			Du er ikke registrert med WhatPulse informasjon. For å koble WhatPulse informasjonen til din profil fyll ut formen nedenfor. Se på <a href="http://whatpulse.org/" target="_blank">whatpulse.org</a> for informasjon om WhatPulse.
		</p>
	</div>
	<div class="section">
		<form action="" method="post">';
	
	// hent informasjon?
	$show_form = true;
	if (isset($_POST['hentWP']))
	{
		$show_form = false;
		
		$id = intval(postval("hentWP"));
		$wp = new whatpulse($id);
		$data = $wp->get_xml();
		
		// kontroller data
		if ($data == "No UserID given!")
		{
			$_base->page->add_message("Ugyldig ID!", "error");
			$show_form = true;
		}
		
		elseif ($data == "Unknow UserID given!")
		{
			$_base->page->add_message("Ingen bruker er opprettet med den ID-en.", "error");
			$show_form = true;
		}
		
		else
		{
			// forsøk å lese data (XML)
			if (!$wp->update($data, false))
			{
				$_base->page->add_message("Ukjent feil oppsto.<br /><br />".htmlspecialchars($data));
				$show_form = true;
			}
			
			else
			{
				$info = array(
					$wp->stat_info("UserID"),
					$wp->stat_info("AccountName"),
					$wp->stat_info("DateJoined")
				);
				
				// godkjent?
				if (isset($_POST['confirm']))
				{
					$params = new params();
					$params->update("fields", "UserID,AccountName,GeneratedTime,DateJoined,Keys,AvKPS,Clicks,AvCPS");
					$params_text = $params->build();
					
					\Kofradia\DB::get()->exec("INSERT INTO stats_whatpulse SET sw_userid = $id, sw_up_id = ".$player->id.", sw_time_add = ".time().", sw_params = ".\Kofradia\DB::quote($params_text));
					putlog("NOTICE", "%c12%bWHATPULSE-OPPRETTELSE:%b%c (".$player->data['up_name'].") la til WhatPulse til sin profil (WPID: %u{$id}%u).");
					
					$_base->page->add_message("Du har nå koblet din WhatPulse konto til din konto her på Kofradia.<br />Du kan nå velge hvilke felt du ønsker å vise på profilen din.");
					redirect::handle();
				}
				
				echo '
			<input type="hidden" name="hentWP" value="'.$id.'" />
			<input type="hidden" name="confirm" />
			<h3>Legg til WhatPulse informasjon (trinn 2)</h3>
			<p class="h_right"><a href="./">Avbryt</a></p>
			<p>
				Her er litt informasjon fra WhatPulse profilen du anga. Hvis dette er din WhatPulse profil som du ønsker å legge til trykk på legg til knappen nederst, eller trykk avbryt over.
			</p>
			<dl class="dl_30">';
				
				$i = 0;
				foreach ($info as $row)
				{
					$color = ++$i % 2 == 0;
					
					echo '
				<dt'.($color ? ' class="color"' : '').'>'.$row[0].'</dt>
				<dd'.($color ? ' class="color"' : '').'>'.$row[1].'</dd>';
				}
				
				echo '
			</dl>
			<h3 class="c">
				'.show_sbutton("Legg til").'
			</h3>';
			}
		}
	}
	
	if ($show_form)
	{
		echo '
			<h3>Legg til WhatPulse informasjon</h3>
			<dl class="dl_45">
				<dt>WhatPulse Bruker ID</dt>
				<dd><input name="hentWP" type="text" value="'.htmlspecialchars(postval("hentWP")).'" class="styled w100" /></dd>
			</dl>
			<h3 class="c">
				'.show_sbutton("Hent informasjon").'
			</h3>';
	}
	
	echo '
		</form>
	</div>
</div>';
}

else
{
	$wpInfo = $result->fetch();
	
	// fjerne fra Kofradia kontoen?
	if (isset($_POST['wpFjern']))
	{
		\Kofradia\DB::get()->exec("DELETE FROM stats_whatpulse WHERE sw_up_id = ".$player->id);
		$_base->page->add_message("WhatPulse informasjonen er nå fjernet fra din konto.");
		putlog("NOTICE", "%c12%bWHATPULSE-FJERNING:%b%c (%u".$player->data['up_name']."%u) fjernet WhatPulse fra sin profil (WPID: %u{$wpInfo['sw_userid']}%u).");
		redirect::handle();
	}
	
	$wp = new whatpulse($wpInfo['sw_userid']);
	$wp->set_user_data($wpInfo);
	$wp->update();
	
	// les data (XML)
	if (!$wp->update())
	{
		echo '
<h1>WhatPulse</h1>
<div style="width: 300px" class="center">
	<div class="section">
		<h3>Feil oppstått med WhatPulse</h3>
		<p>Noe gikk feil ved henting og lesing av data fra WhatPulse serveren.</p>
		<p>Du kan forsøke å laste inn data med en annen brukerkonto ved å fjerne tilknytningen til WhatPulse og deretter legge den til på nytt.</p>
		<form action="" method="post">
			<p><input type="submit" name="wpFjern" value="Fjern fra Kofradia kontoen" class="button" onclick="return confirm(\'Er du sikker på at du ønsker å fjerne WhatPulse informasjonen fra Kofradia kontoen din?\')" /></p>
		</form>
	</div>
</div>';
	}
	
	else
	{
		// hent ut hvilke felt vi skal vise
		$fields = $wp->params->get("fields");
		if (empty($fields)) $fields = array(); //$fields = "UserID,AccountName,GeneratedTime,DateJoined,TotalKeyCount,AvKPS,TotalMouseClicks,AvCPS";
		else $fields = explode(",", $fields);
		
		// endre felt?
		if (isset($_POST['wpFelt']))
		{
			$felt = postval("wpFelt");
			
			// ugyldig?
			if (mb_substr($felt, 0, 5) != "felt:")
			{
				$_base->page->add_message("Det ser ut som du ikke har JavaScript aktivert i din nettleser.", "error");
			}
			
			else
			{
				$felt = explode(",", mb_substr($felt, 5));
				$aktive = array();
				
				foreach ($felt as $name)
				{
					// seperator?
					if ($name == "-")
					{
						if (count($aktive) > 0 && end($aktive) != "-") $aktive[] = "-";
					}
					
					// finnes feltet?
					if (isset($wpFelt[$name]) && !in_array($name, $aktive))
					{
						$aktive[] = $name;
					}
				}
				
				// er siste en seperator?
				if (end($aktive) == "-") array_pop($aktive);
				
				// lagre
				$wp->params->update("fields", implode(",", $aktive));
				$data = $wp->params->build();
				
				\Kofradia\DB::get()->exec("UPDATE stats_whatpulse SET sw_params = ".\Kofradia\DB::quote($data)." WHERE sw_up_id = ".$player->id);
				$_base->page->add_message("WhatPulse informasjonen ble oppdatert. Sjekk profilen din!");
			}
			
			redirect::handle();
		}
		
		// javascript
		$_base->page->add_js('
function wpHentFelt(form)
{
	var elms = [];
	var op = form.wpAktiveFelt.options;
	
	for (var i = 0; i < op.length; i++)
	{
		elms.push(op[i].value);
	}
	
	form.wpFelt.value = "felt:" + elms.join(",");
}
function wpFlyttOpp(form)
{
	var obj = form.wpAktiveFelt;
	var index = obj.selectedIndex;
	if (index <= 0) return;
	
	var val0 = [obj.options[index].value, obj.options[index].text];
	var val1 = [obj.options[index-1].value, obj.options[index-1].text];
	
	obj.options[index] = new Option(val1[1], val1[0]);
	obj.options[index-1] = new Option(val0[1], val0[0]);
	
	obj.selectedIndex = index - 1;
}
function wpFlyttNed(form)
{
	var obj = form.wpAktiveFelt;
	var index = obj.selectedIndex;
	if (index == obj.options.length - 1 || index < 0) return;
	
	var val0 = [obj.options[index].value, obj.options[index].text];
	var val1 = [obj.options[index+1].value, obj.options[index+1].text];
	
	obj.options[index] = new Option(val1[1], val1[0]);
	obj.options[index+1] = new Option(val0[1], val0[0]);
	
	obj.selectedIndex = index + 1;
}
function wpSettInaktiv(form)
{
	var objA = form.wpAktiveFelt;
	var objI = form.wpInaktiveFelt;
	
	var index = objA.selectedIndex;
	if (index < 0) return;
	
	if (objA.options[index].value != "-")
		objI.options[objI.options.length] = new Option(objA.options[index].text, objA.options[index].value);
	objA.options[index] = null;
	
	objA.selectedIndex = index > objA.options.length - 1 ? objA.options.length - 1 : index;
}
function wpSettAktiv(form)
{
	var objA = form.wpAktiveFelt;
	var objI = form.wpInaktiveFelt;
	
	var index = objI.selectedIndex;
	if (index < 0) return;
	
	objA.options[objA.options.length] = new Option(objI.options[index].text, objI.options[index].value);
	objI.options[index] = null;
	
	objI.selectedIndex = index > objI.options.length - 1 ? objI.options.length - 1 : index;
}
function wpNySeperator(form)
{
	var obj = form.wpAktiveFelt;
	
	var index = obj.selectedIndex;
	if (index < 0) index = 0;
	
	if (index == 0 || obj.options[index-1].value == "-" || obj.options[index].value == "-") return;
	
	for (var i = obj.options.length; i > index; i--)
	{
		obj.options[i] = new Option(obj.options[i-1].text, obj.options[i-1].value);
	}
	
	obj.options[index] = new Option("---", "-");
	obj.selectedIndex = index;
}');
		
		$info = array(
			$wp->stat_info("UserID"),
			$wp->stat_info("AccountName"),
			$wp->stat_info("DateJoined")
		);
		
		echo '
<h1>WhatPulse</h1>
<div style="width: 300px; float: left; padding-left: 25px">
	<form action="" method="post">
		<div class="section">
			<h3>Generelt</h3>
			<dl class="dl_30 dl_2x">';
		
		foreach ($info as $row)
		{
			echo '
				<dt>'.$row[0].'</dt>
				<dd>'.$row[1].'</dd>';
		}
		
		echo '
					
				<dt>Profil</dt>
				<dd><a href="http://whatpulse.org/'.$wp->user_id.'" target="_blank">Vis WP profil</a></dd>
				
				<dt>Fjern</dt>
				<dd><input type="submit" name="wpFjern" value="Fjern fra Kofradia kontoen" class="button" onclick="return confirm(\'Er du sikker på at du ønsker å fjerne WhatPulse informasjonen fra Kofradia kontoen din?\')" /></dd>
			</dl>
		</div>
	</form>
	<form action="" method="post" onsubmit="return wpHentFelt(this)">
		<input type="hidden" name="wpFelt" value="" />
		<div class="section">
			<h3>Felt</h3>
			<dl>
				<dt>Aktive felt</dt>
				<dd class="r">
					<input type="button" value="Opp" onclick="wpFlyttOpp(this.form)" class="button" />
					<input type="button" value="Ned" onclick="wpFlyttNed(this.form)" class="button" />
					<input type="button" value="---" onclick="wpNySeperator(this.form)" class="button" />
					<input type="button" value="Inaktiv" onclick="wpSettInaktiv(this.form)" class="button" />
				</dd>
			</dl>
			<p>
				<select name="wpAktiveFelt" size="10" style="width: 100%">';
		
		foreach ($fields as $name)
		{
			if ($name == "-")
			{
				$title = "---";
			}
			else
			{
				if (!isset($wpFelt[$name])) continue;
				$title = $wpFelt[$name];
				unset($wpFelt[$name]);
			}
			
			echo '
					<option value="'.htmlspecialchars($name).'" selected="selected">'.htmlspecialchars($title).'</option>';
		}
		
		echo '
				</select>
			</p>
			<dl>
				<dt>Inaktive felt</dt>
				<dd class="r"><input type="button" value="Aktiv" onclick="wpSettAktiv(this.form)" class="button" /></dd>
			</dl>
			<p>
				<select name="wpInaktiveFelt" size="10" style="width: 100%">';
		
		foreach ($wpFelt as $name => $title)
		{
			echo '
					<option value="'.htmlspecialchars($name).'">'.htmlspecialchars($title).'</option>';
		}
		
		echo '
				</select>
			</p>
			<h3 class="c">
				'.show_sbutton("Oppdater").'
			</h3>
		</div>
	</form>
</div>
<div class="wp" style="width: 250px; float: left; padding-left: 30px">
	<div class="section">';
		
		// har vi noe å vise?
		if (count($fields) > 0)
		{
			#$_base->page->add_css('.wp dd { text-align: center } .wp dl { margin: 0 -10px } .wp dt, .wp dd { padding-left: 8px; padding-right: 8px }');
			$_base->page->add_css('.wp dl { margin: 8px 0 }');
			
			// vis info
			echo '
		<h3>WhatPulse informasjon</h3>
		<dl class="dl_50">';
			
			foreach ($fields as $fieldname)
			{
				$info = $wp->stat_info($fieldname);
				
				echo '
			<dt>'.$info[0].'</dt>
			<dd>'.$info[1].'</dd>';
			}
			
			echo '
		</dl>';
		}
		
		else
		{
			echo '
		<h3>WhatPulse informasjon</h3>
		<p><b>Informasjon:</b></p>
		<p>Du har ikke aktivert noen felt og viser derfor ingen WhatPulse informasjon!</p>';
		}
		
		echo '
	</div>
</div>';
	}
}

$_base->page->load();