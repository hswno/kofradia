<?php

require "../../base.php";
error_reporting(E_ALL & ~E_NOTICE);

global $_game;

// anti-bot
#$antibot_name = "poker";
#$antibot_span = 30;
#$antibot_return = PHP_SELF;
#antibot::require_antibot($antibot_name, $antibot_span, $antibot_return);

ess::$b->page->add_title("Moderator poker");
access::need_nostat();
access::need("forum_mod");

// legg til css
ess::$b->page->add_css("#poker_info { margin: 10px auto 0 auto }
.kort_wrapper { width: 257px; padding: 4px 0 text-align: center }
#kort_wrapper { width: 260px; margin: 0 auto 0 auto; padding-bottom: 10px }
.kort_wrapper .kort, .kort_i {
	font-family: Arial, Verdana, Tahoma;
	width: 45px;
	text-align: center;
	border: 1px solid #000;
	background: #FFFFFF;
	height: 85px;
	font-size: 30px;
	padding: 0 0 5px 0;
	margin: 0 2px 0 2px;
	line-height: 50px;
	float: left;
}
.kort_wrapper .kort {
	background: #488C9F;
	cursor: pointer;
}
.kg_0, .kg_1 { color: #000000 }
.kg_2, .kg_3 { color: #FF0000 }
.kort_submit { clear: left; text-align: center; margin-top: 0 }");

echo '
<h1>Poker</h1>
<p align="center">
	15. april 2007: Da kan moderatorene og nostat ha det morsomt med den gamle pokerversjonen!
</p>
<!--<p>
	<b style="color: #FF0000">Oppdatering 4. mars:</b> Nå er det gjort litt endringer på gevinstene og man vinner kun på 1 par dersom man har 9, 10, J, Q, K eller A. Maksimumsgrensen er også fjernet.
</p>-->';

// 5-korts draw

// innstillinger
$farger = array(
	/*array("Kløver", "&clubs;"),
	array("Spar", "&spades;"),
	array("Hjerter", "&hearts;"),
	array("Ruter", "&diams;")*/
	array("Kløver",		'<img src="'.STATIC_LINK.'/other/poker_clubs.gif" alt="Kløver" />'),
	array("Spar",		'<img src="'.STATIC_LINK.'/other/poker_spades.gif" alt="Spar" />'),
	array("Hjerter",	'<img src="'.STATIC_LINK.'/other/poker_hearts.gif" alt="Hjerter" />'),
	array("Ruter",		'<img src="'.STATIC_LINK.'/other/poker_diams.gif" alt="Ruter" />')
);
$tegn = array(
	1 => 2,3,4,5,6,7,8,9,10,"J","Q","K","A"
);
$gevinster = array(
	array(9, "royal straight flush", 100),	// de fem høyste kortene i samme farge
	array(8, "straight flush", 12),			// fem kort etter hverandre i samme farge
	array(7, "fire like", 4),				// fire like kort
	array(6, "hus", 3),						// tre like og ett par
	array(5, "flush", 2.4),					// alle kortene i samme farge
	array(4, "straight", 2.2),				// fem kort etter hverandre
	array(3, "tre like", 1.9),				// tre like
	array(2, "to par", 1.6),				// to par
	array(1, "ett par", 1.2),				// ett par
	array(0, "ingenting", 0.2)				// ingenting
);

$kortstokk = array(1 => 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52);


// oppføring fra databasen?
$result = ess::$b->db->query("SELECT id, upoker_up_id, cards, time, cash, finished FROM users_poker WHERE upoker_up_id = ".login::$user->player->id);
$poker = false;
if (mysql_num_rows($result) > 0)
{
	$poker = mysql_fetch_assoc($result);
}

// de første kortene
if (isset($_POST['amount']))
{
	if ($poker)
	{
		ess::$b->page->add_message("Du er allerede i gang med et spill. Fullfør det før du begynner på et nytt.", "error");
		redirect::handle();
	}
	
	// pengebeløpet
	$amount = game::intval($_POST['amount']);
	if ($amount > login::$user->player->data['up_cash'])
	{
		ess::$b->page->add_message("Du har ikke så mye penger på hånda!", "error");
		redirect::handle();
	}
	
	// for lite beløp?
	elseif ($amount < 200)
	{
		ess::$b->page->add_message("Du må satse minimum 200 kr!", "error");
		redirect::handle();
	}
	
	$_SESSION[$GLOBALS['__server']['session_prefix'].'poker_siste_innsats'] = $amount;
	
	// finn 5 tilfeldige kort
	$kort = array_rand($kortstokk, 5);
	
	// sett inn i databasen
	ess::$b->db->query("INSERT INTO users_poker SET upoker_up_id = ".login::$user->player->id.", cards = '".implode(",", $kort)."', time = ".time().", cash = $amount");
	
	// fjern pengene fra brukeren
	ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $amount WHERE up_id = ".login::$user->player->id);
	
	ess::$b->page->add_message("Du har startet et spill med innsats pålydende ".game::format_cash($amount)."!");
	redirect::handle();
}

// vis start formen?
elseif (!$poker)
{
	$siste_innsats = game::intval($_SESSION[$GLOBALS['__server']['session_prefix'].'poker_siste_innsats'] * 1.1);
	if ($siste_innsats == 0) $siste_innsats = 1000;
	if ($siste_innsats > login::$user->player->data['up_cash']) $siste_innsats = login::$user->player->data['up_cash'];
	#if ($siste_innsats > 1000000000000) $siste_innsats = 1000000000000;
	
	echo '
<form action="" method="post">
	<table class="table game center">
		<thead>
			<tr>
				<th colspan="3">Poker</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><b>Innsats</b></td>
				<td><input type="text" class="styled w120" name="amount" value="'.game::format_number($siste_innsats).'" /></td><td>'.show_button("Alt", 'onclick="this.parentNode.previousSibling.firstChild.value=\''.game::format_cash(login::$user->player->data['up_cash']).'\'"').'</td>
			</tr>
			<tr>
				<td colspan="3" align="center">'.show_sbutton("Start spillet!").'</td>
			</tr>
		</tbody>
	</table>
</form>
'.gevinster();
}


// runde 2
else
{
	// sett opp kortene
	$kort = array();
	$arr = explode(",", $poker['cards']);
	foreach ($arr as $kortnum)
	{
		// fjern fra kortstokken
		unset($kortstokk[$kortnum]);
		
		// hvilken gruppe (0-3)
		$gruppe = ceil($kortnum/13) - 1;
		
		// hvilket kortnummer (1-13)
		$num = $kortnum - $gruppe * 13;
		
		// legg til kortet
		$kort[] = array($num, $gruppe, $kortnum);
	}
	
	
	// vis resultater..
	if ($poker['finished'] == 1)
	{
		// slett fra databasen
		ess::$b->db->query("DELETE FROM users_poker WHERE id = {$poker['id']}");
		
		$siste_innsats = game::intval($_SESSION[$GLOBALS['__server']['session_prefix'].'poker_siste_innsats'] * 1.1);
		if ($siste_innsats == 0) $siste_innsats = 1000;
		if ($siste_innsats > login::$user->player->data['up_cash']) $siste_innsats = login::$user->player->data['up_cash'];
		#if ($siste_innsats > 1000000000000) $siste_innsats = 1000000000000;
		
		echo '

<p align="center">
	<b>Resultat:</b>
</p>
<div class="kort_wrapper">
	<div class="kort_i kg_'.$kort[0][1].'">'.$tegn[$kort[0][0]].'<br />'.$farger[$kort[0][1]][1].'</div>
	<div class="kort_i kg_'.$kort[1][1].'">'.$tegn[$kort[1][0]].'<br />'.$farger[$kort[1][1]][1].'</div>
	<div class="kort_i kg_'.$kort[2][1].'">'.$tegn[$kort[2][0]].'<br />'.$farger[$kort[2][1]][1].'</div>
	<div class="kort_i kg_'.$kort[3][1].'">'.$tegn[$kort[3][0]].'<br />'.$farger[$kort[3][1]][1].'</div>
	<div class="kort_i kg_'.$kort[4][1].'">'.$tegn[$kort[4][0]].'<br />'.$farger[$kort[4][1]][1].'</div>
</div>
<form action="" method="post">
	<table class="table game center" style="clear: left; margin-top: 10px">
		<thead>
			<tr>
				<th colspan="3">Nytt spill</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><b>Innsats</b></td>
				<td><input type="text" class="styled w120" name="amount" value="'.game::format_number($siste_innsats).'" /></td><td>'.show_button("Alt", 'onclick="this.parentNode.previousSibling.firstChild.value=\''.game::format_cash(login::$user->player->data['up_cash']).'\'"').'</td>
			</tr>
			<tr>
				<td colspan="3" align="center">'.show_sbutton("Start spillet!").'</td>
			</tr>
		</tbody>
	</table>
</form>

'.gevinster();
		
	}
	
	
	// fortsette på gammel poker
	else
	{	
		// fullføre?
		if (isset($_POST['fullfor']))
		{
			// beholde noen bilder?
			$hente = array(0,1,2,3,4);
			if (isset($_POST['kort']) && is_array($_POST['kort']))
			{
				// gå gjennom hver og fjern fra den vi skal beholde
				for ($i = 0; $i < 5; $i++)
				{
					if (isset($_POST['kort'][$i])) unset($hente[$i]);
				}
			}
			
			// hent nye kort
			foreach ($hente as $hent)
			{
				$rand = array_rand($kortstokk);
				unset($kortstokk[$rand]);
				$gruppe = ceil($rand/13) - 1;					// hvilken gruppe (0-3)
				$kortnum = $rand - $gruppe * 13;				// hvilket kortnummer (1-13)
				$kort[$hent] = array($kortnum, $gruppe, $rand);
			}
			
			// sjekk resultatet
			$kortnummer = array($kort[0][0], $kort[1][0], $kort[2][0], $kort[3][0], $kort[4][0]);
			sort($kortnummer);
			
			$kortfarger = array($kort[0][1], $kort[1][1], $kort[2][1], $kort[3][1], $kort[4][1]);
			sort($kortfarger);
			
			$kortfarger_like = $kortfarger[0] == $kortfarger[4];
			
			$straight = $kortnummer[0] == ($kortnummer[1]-1) && $kortnummer[0] == ($kortnummer[2]-2) && $kortnummer[0] == ($kortnummer[3]-3) && ($kortnummer[0] == ($kortnummer[4]-4) || ($kortnummer[0] == 1 && $kortnummer[4] == 13));
			
			$won = false;
			
			// sjekk om vi har royal straight flush
			if ($kortfarger_like && $kortnummer[0] == 9 && $kortnummer[1] == 10 && $kortnummer[2] == 11 && $kortnummer[3] == 12 && $kortnummer[4] == 13)
			{
				$won = $gevinster[0];
				
				// IRC melding
				putlog("INFO", "%c4%bROYAL STRAIGHT FLUSH%b: %u".login::$user->player->data['up_name']."%u fikk royal straight flush!");
			}
			
			// sekk om vi har straight flush
			elseif ($kortfarger_like && $straight)
			{
				$won = $gevinster[1];
			}
			
			// sjekk om vi har fire like
			elseif (($kortnummer[1] == $kortnummer[3]) && ($kortnummer[1] == $kortnummer[0] || $kortnummer[1] == $kortnummer[4]))
			{
				$won = $gevinster[2];
			}
			
			// sjekk om vi har hus
			elseif ($kortnummer[0] == $kortnummer[1] && $kortnummer[3] == $kortnummer[4] && ($kortnummer[0] == $kortnummer[2] || $kortnummer[4] == $kortnummer[2]))
			{
				$won = $gevinster[3];
			}
			
			// sjekk om vi har flush
			elseif ($kortfarger_like)
			{
				$won = $gevinster[4];
			}
			
			// sjekk om vi har straight
			elseif ($straight)
			{
				$won = $gevinster[5];
			}
			
			// sjekk om vi har tre like
			elseif ($kortnummer[0] == $kortnummer[2] || $kortnummer[1] == $kortnummer[3] || $kortnummer[2] == $kortnummer[4])
			{
				$won = $gevinster[6];
			}
			
			// sjekk om vi har to par
			elseif (($kortnummer[0] == $kortnummer[1] && ($kortnummer[2] == $kortnummer[3] || $kortnummer[3] == $kortnummer[4])) || (($kortnummer[0] == $kortnummer[1] || $kortnummer[1] == $kortnummer[2]) && $kortnummer[3] == $kortnummer[4]))
			{
				$won = $gevinster[7];
			}
			
			// sjekk om vi har ett par
			elseif ($kortnummer[0] == $kortnummer[1] || $kortnummer[1] == $kortnummer[2] || $kortnummer[2] == $kortnummer[3] || $kortnummer[3] == $kortnummer[4])
			{
				$won = $gevinster[8];
			}
			
			
			// (($kortnummer[0] == $kortnummer[1] || $kortnummer[1] == $kortnummer[2] || $kortnummer[2] == $kortnummer[3] || $kortnummer[3] == $kortnummer[4])
			
			//$log = "Stian";
			
			// vant
			if ($won)
			{
				// vant noe!
				$cash = $poker['cash'] * $won[2];
				ess::$b->page->add_message("Du fikk <b>".$won[1]."</b> og vant <b>".game::format_cash($cash)."</b>!");
				
				// statistikk id
				$stat_id = intval($won[0]);
				
				$rest = $cash-$poker['cash'];
				putlog("SPAMLOG", "%bPOKER%b: (%u".login::$user->player->data['up_name']."%u) satset (".game::format_cash($poker['cash'])."), fikk ({$won[1]}) og vant (".game::format_cash($cash).") (%c".($rest < 100000000 ? 13 : ($rest < 1000000000 ? 9 : 4))."+".game::format_cash($rest)."%c)");
				
				// gi pengene til brukeren
				ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + $cash WHERE up_id = ".login::$user->player->id);
			}
			
			
			// tapte
			else
			{
				// statistikk id
				$stat_id = 0;
				
				#$cash = $poker['cash'] * $gevinster[9][2];
				$cash = $poker['cash'];
				
				$rest = $cash-$cash*$gevinster[9][2];
				putlog("SPAMLOG", "%bPOKER%b: (%u".login::$user->player->data['up_name']."%u) satset (%u".game::format_cash($poker['cash'])."%u), fikk (%uingenting%u) og fikk tilbake (%u".game::format_cash($cash*$gevinster[9][2])."%u) (%c".($rest < 100000000 ? 13 : ($rest < 1000000000 ? 9 : 4))."-".game::format_cash($rest)."%c)");
				
				// fikk vi egentlig ett par?
				#if ($kortnummer[0] == $kortnummer[1] || $kortnummer[1] == $kortnummer[2] || $kortnummer[2] == $kortnummer[3] || $kortnummer[3] == $kortnummer[4])
				#{
				#	ess::$b->page->add_message("Du fikk ett par, men kombinasjonen var ikke lik eller større enn <b>9</b> og du tapte innsatsen på <b>".game::format_cash($poker['cash'])."</b>! Som trøst fikk du tilbake ".($gevinster[9][2]*100)." %, altså ".game::format_cash($poker['cash']*$gevinster[9][2])."!");
				#}
				#else
				#{
					ess::$b->page->add_message("Du fikk ingen kombinasjoner og tapte innsatsen på <b>".game::format_cash($poker['cash'])."</b>!<br />Som trøst fikk du tilbake <b>".($gevinster[9][2]*100)." %</b>, altså <b>".game::format_cash($poker['cash']*$gevinster[9][2])."</b>!");
				#}
				
				// gi trøstepengene til brukeren
				ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + ".($cash*$gevinster[9][2])." WHERE up_id = ".login::$user->player->id);
			}
			
			// oppdater statistikken
			ess::$b->db->query("UPDATE stats SET count = count + 1, count2 = count2 + $cash WHERE area = 'poker' AND name = 'alt' AND stats_up_id = ".login::$user->player->id." AND subname = $stat_id");
			if (ess::$b->db->affected_rows() == 0)
			{
				// opprett
				ess::$b->db->query("INSERT INTO stats SET area = 'poker', name = 'alt', stats_up_id = ".login::$user->player->id.", subname = $stat_id, count = 1, count2 = $cash");
			}
			
			// oppdater totalt statistikken
			if (login::$user->player->data['up_access_level'] < $_game['access_noplay'])
			{
				ess::$b->db->query("UPDATE stats SET count = count + 1, count2 = count2 + $cash WHERE area = 'poker' AND name = 'alt' AND stats_up_id = 0 AND subname = $stat_id");
				if (ess::$b->db->affected_rows() == 0)
				{
					// opprett
					ess::$b->db->query("INSERT INTO stats SET area = 'poker', name = 'alt', stats_up_id = 0, subname = $stat_id, count = 1, count2 = $cash");
				}
			}
			
			
			// oppdater poker tabellen
			$arr = array();
			foreach ($kort as $v)
			{
				$arr[] = $v[2];
			}
			ess::$b->db->query("UPDATE users_poker SET cards = '".implode(",", $arr)."', finished = 1 WHERE id = {$poker['id']}");
			
			// oppdater anti-bot
			#antibot::inc($antibot_name);
			
			// send brukeren til resultatet..
			redirect::handle();
		}
		
		// legg til js filen
		ess::$b->page->add_js_file(ess::$s['relative_path']."/js/poker.js");
		
		echo '
<form action="" method="post">
	<p align="center">
		Din innsats: '.game::format_cash($poker['cash']).'
	</p>
	<p align="center">
		<b>Marker de kortene du vil <u>beholde</u>:</b>
	</p>
	<div class="kort_wrapper" >
		<div class="kort kg_'.$kort[0][1].'">'.$tegn[$kort[0][0]].'<br />'.$farger[$kort[0][1]][1].'<input type="checkbox" name="kort[0]" /></div>
		<div class="kort kg_'.$kort[1][1].'">'.$tegn[$kort[1][0]].'<br />'.$farger[$kort[1][1]][1].'<input type="checkbox" name="kort[1]" /></div>
		<div class="kort kg_'.$kort[2][1].'">'.$tegn[$kort[2][0]].'<br />'.$farger[$kort[2][1]][1].'<input type="checkbox" name="kort[2]" /></div>
		<div class="kort kg_'.$kort[3][1].'">'.$tegn[$kort[3][0]].'<br />'.$farger[$kort[3][1]][1].'<input type="checkbox" name="kort[3]" /></div>
		<div class="kort kg_'.$kort[4][1].'">'.$tegn[$kort[4][0]].'<br />'.$farger[$kort[4][1]][1].'<input type="checkbox" name="kort[4]" /></div>
		<div style="clear: left"></div>
	</div>
	<p class="kort_submit">
		'.show_sbutton("Fortsett!", 'name="fullfor"').'
	</p>
</form>
'.gevinster().'
<script type="text/javascript">
<!--
poker.init();
// -->
</script>';
	}
}

function gevinster()
{
	global $gevinster;
	
	$stats = array();
	
	// hent total statistikk
	$result = ess::$b->db->query("SELECT subname, count, count2 FROM stats WHERE area = 'poker' AND name = 'alt' AND stats_up_id = 0");
	while ($row = mysql_fetch_assoc($result))
	{
		$stats[$row['subname']]['total'] = $row['count'];
		$stats[$row['subname']]['total_cash'] = $row['count2'];
	}
	
	// hent bruker statistikk
	$result = ess::$b->db->query("SELECT subname, count, count2 FROM stats WHERE area = 'poker' AND name = 'alt' AND stats_up_id = ".login::$user->player->id);
	while ($row = mysql_fetch_assoc($result))
	{
		$stats[$row['subname']]['count'] = $row['count'];
		$stats[$row['subname']]['cash'] = $row['count2'];
	}
	
	// skriv tabell
	$ret = '
<h1>Statistikk</h1>
<table class="table game" id="poker_info">
	<thead>
		<tr>
			<th>Navn</th>
			<th>Penger</th>
			<th>Stats</th>
		</tr>
	</thead>
	<tbody>';
		
		$i = 0;
		$totalt_cash_1 = 0;
		$totalt_cash_2 = 0;
		$totalt_stat_1 = 0;
		$totalt_stat_2 = 0;
		
		foreach ($gevinster as $gevinst)
		{
			if (isset($stats[$gevinst[0]]))
			{
				$totalt_stat_1 += $stats[$gevinst[0]]['count'];
				$totalt_stat_2 += $stats[$gevinst[0]]['total'];
				$stat = '<b>'.game::format_number($stats[$gevinst[0]]['count']).'</b> ('.game::format_number($stats[$gevinst[0]]['total']).')';
				$cash_total = $stats[$gevinst[0]]['total_cash'];
				$cash = $stats[$gevinst[0]]['cash'];
			}
			else
			{
				$stat = '<b>0</b> (0)';
				$cash_total = 0;
				$cash = 0;
			}
			
			if ($gevinst[0] == 0)
			{
				$cash = $cash * -1;
				$cash_total = $cash_total * -1;
			}
			
			$totalt_cash_1 += $cash;
			$totalt_cash_2 += $cash_total;
			
			$cash = game::format_cash($cash).' ('.game::format_cash($cash_total).')';
			
			$ret .= '
		<tr'.(is_int(++$i/2) ? ' class="color"' : '').'>
			<td>'.htmlspecialchars(ucfirst($gevinst[1])).'</td>
			<td align="right"><span style="color: #999">Innsats *</span> '.game::format_number($gevinst[2], 2).'</td>
			<td align="right" title="'.$cash.'">'.$stat.'</td>
		</tr>';
		}
		
		if (isset($stats[0]))
		{
			$stat = '<b>'.game::format_number($stats[0]['count']).'</b> ('.game::format_number($stats[0]['total']).')';
			$cash_total = $stats[0]['total_cash'];
			$cash = $stats[0]['cash'];
		}
		else
		{
			$stats = '<b>0</b> (0)';
			$cash_total = 0;
			$cash = 0;
		}
		$cash = game::format_cash($cash).' ('.game::format_cash($cash_total).')';
		
		$stat = '<b>'.game::format_number($totalt_stat_1).'</b> ('.game::format_number($totalt_stat_2).')';
		$cash = game::format_cash($totalt_cash_1).' ('.game::format_cash($totalt_cash_2).')';
		$ret .= '
		<tr class="spacer"><td colspan="3">&nbsp;</td></tr>
		<tr'.(is_int($i/2) ? ' class="color"' : '').'>
			<td><b>Totalt</b></td>
			<td>&nbsp;</td>
			<td align="right" title="'.$cash.'">'.$stat.'</td>
		</tr>
		<tr class="spacer"><td colspan="3">&nbsp;</td></tr>
		<tr'.(is_int(++$i/2) ? ' class="color"' : '').'>
			<td colspan="3"><b>Tips</b>: Hold musa over stats feltet for å se pengestats!</td>
		</tr>
	</tbody>
</table>';
	
	return $ret;
}

function royale($num)
{
	return $num == 0 || $num > 7;
}

ess::$b->page->load();