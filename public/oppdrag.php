<?php

require "base.php";
global $_base;

/*if (isset($_GET['fiks']))
{
	#\Kofradia\DB::get()->exec("UPDATE kriminalitet SET max_strength = RAND()*10 + 1, wait_time = RAND()*5 + 1");
	\Kofradia\DB::get()->exec("UPDATE users_oppdrag SET uo_last_time = 0, uo_locked = 0 WHERE uo_o_id AND uo_up_id = ".login::$user->player->id);
	\Kofradia\DB::get()->exec("UPDATE users_players SET up_fengsel_time = UNIX_TIMESTAMP() WHERE up_id = ".login::$user->player->id);
	
	$_base->page->add_message("Hvis du var i fengsel, er du nå ute. Du kan begynne på alle oppdrag du har på listen nedenfor. (Ventetid osv nullstilt.)");
	redirect::handle();
}*/

$_base->page->add_title("Oppdrag");

login::$user->player->fengsel_require_no();
login::$user->player->bomberom_require_no();

// TODO: energi i oppdrag?

// fjerne alle mine oppdrag
/*if (isset($_POST['delete_oppdrag']))
{
	\Kofradia\DB::get()->exec("DELETE FROM users_oppdrag WHERE uo_up_id = ".login::$user->player->id);
	redirect::handle();
}*/

#login::$user->player->oppdrag->user_load_all();

// er vi på et aktivt oppdrag?
if (login::$user->player->oppdrag->active)
{
	$oppdrag = login::$user->player->oppdrag->active;
	$trigger = login::$user->player->oppdrag->params[$oppdrag['o_id']]['o_params'];
	$status = login::$user->player->oppdrag->params[$oppdrag['o_id']]['uo_params'];
	$expire = $oppdrag['uo_active_time']+$trigger->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
	
	// tittel
	$_base->page->add_title($oppdrag['o_title']);
	
	// vise en bestemt side?
	if (!isset($_GET['force']))
	{
		switch ($trigger->get("name"))
		{
			case "single_poker":
				require PATH_APP."/game/oppdrag/single_poker.php";
				$_base->page->load();
			break;
		}
	}
	
	// avbryte oppdraget?
	if (isset($_POST['abort']))
	{
		// feil o_id?
		if (postval("o_id") != $oppdrag['o_id']) redirect::handle();
		
		// godkjent?
		if (isset($_POST['confirm']))
		{
			login::$user->player->oppdrag->failed($oppdrag['o_id'], 'Du avbrøt oppdraget &laquo;$name&raquo;. Oppdraget ble derfor mislykket.');
			$_base->page->add_message("Du avbrøt oppdraget. Oppdraget ble derfor mislykket.");
			redirect::handle();
		}
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">Avbryte &laquo;'.htmlspecialchars($oppdrag['o_title']).'&raquo;<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Hvis du avbryter oppdraget vil oppdraget bli mislykket. Du må da vente 1 time før du kan forsøke dette på dette oppdraget igjen. I tillegg vil du komme i fengsel i 15 minutter.</p>
		<form action="" method="post">
			<input type="hidden" name="o_id" value="'.$oppdrag['o_id'].'" />
			<input type="hidden" name="confirm" value="1" />
			<p class="c">'.show_sbutton("Avbryt oppdrag", 'name="abort"').' - <a href="oppdrag" class="button">Ikke avbryt oppdraget</a></p>
		</form>
	</div>
</div>';
		
		$_base->page->load();
	}
	
	echo '
<div class="bg1_c medium">
	<h1 class="bg1">'.htmlspecialchars($oppdrag['o_title']).'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<dl class="dl_15">
			<dt>Påbegynt</dt>
			<dd>'.$_base->date->get($oppdrag['uo_active_time'])->format(date::FORMAT_SEC).'</dd>
			<dt>Tidsgrense</dt>
			<dd>'.$_base->date->get($expire)->format(date::FORMAT_SEC).' ('.game::timespan($expire, game::TIME_ABS).')</dd>
		</dl>';
	
	// spesielt oppdrag?
	switch ($trigger->get("name"))
	{
		case "single_poker":
			echo '
		<p class="c" style="font-size: 16px"><a href="oppdrag">Gå til pokeren</a></p>';
		break;
	}
	
	echo '
		<div class="p">'.login::$user->player->oppdrag->get_description($oppdrag['o_id']).'</div>'.login::$user->player->oppdrag->status(login::$user->player->oppdrag->active['o_id']).'
		<form action="" method="post">
			<input type="hidden" name="o_id" value="'.$oppdrag['o_id'].'" />
			<p class="c">'.show_sbutton("Avbryt oppdrag", 'name="abort"').'</p>
		</form>
	</div>
</div>';
	
	$_base->page->load();
}

// hent oppdragene
login::$user->player->oppdrag->user_load_all();

// starte på et nytt oppdrag
if (isset($_GET['o_id']))
{
	$o_id = (int) getval("o_id");
	
	// kontroller oppdraget
	login::$user->player->oppdrag; // last inn oppdrag om det ikke er lasta inn
	if (!isset(login::$user->player->oppdrag->oppdrag[$o_id]) || login::$user->player->oppdrag->oppdrag[$o_id]['uo_locked'] != 0)
	{
		redirect::handle();
	}
	$oppdrag = login::$user->player->oppdrag->oppdrag[$o_id];
	
	// ikke gått lang nok tid?
	if ($oppdrag['uo_last_state'] == 0 && $oppdrag['uo_last_time']+$oppdrag['o_retry_wait'] > time())
	{
		redirect::handle();
	}
	
	// godkjent?
	if (isset($_POST['start']))
	{
		// sett oppdraget som aktivt
		if (!login::$user->player->oppdrag->active_set($o_id))
		{
			redirect::handle();
		}
		
		// sett nødvendige verdier
		if (isset(login::$user->player->oppdrag->triggers_id[$o_id]))
		{
			$trigger = login::$user->player->oppdrag->triggers_id[$o_id];
			
			switch ($trigger['trigger']->get("name"))
			{
				case "rank_points":
					$trigger['status']->update("target_points", login::$user->player->data['up_points']+$trigger['trigger']->get("points"));
					login::$user->player->oppdrag->update_status($trigger['o_id'], $trigger['status']);
				break;
				
				case "single_poker":
					$trigger['status']->update("chips", $trigger['trigger']->get("chips_start"));
					login::$user->player->oppdrag->update_status($trigger['o_id'], $trigger['status']);
				break;
			}
		}
		
		redirect::handle();
	}
	
	echo '
<div class="bg1_c medium">
	<h1 class="bg1">'.htmlspecialchars($oppdrag['o_title']).'<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<div class="information">
			<p><b>Merk:</b> Du må trykke &laquo;Start oppdrag&raquo; knappen nederst på denne siden før oppdraget blir gjeldende.</p>
		</div>
		<p><b>Beskrivelse:</b></p>
		<div class="p">'.login::$user->player->oppdrag->get_description($oppdrag['o_id']).'</div>
		<p><b>Merk:</b></p>
		<p>Hvis du mislykker oppdraget, må du vente 1 time før du kan utføre oppdraget på nytt. I tillegg kommer du i fengsel i 15 minutter. Hvis du avbryter oppdraget vil det tilsvare at oppdraget blir mislykket.</p>
		<form action="" method="post">
			<input type="hidden" name="o_id" value="'.$oppdrag['o_id'].'" />
			<p class="c">'.show_sbutton("Start oppdrag", 'name="start"').' - <a href="oppdrag" class="button">Avbryt</a></p>
		</form>
	</div>
</div>';
	
	$_base->page->load();
}

$_base->page->add_css('
.oppdrag_list_uo {
	margin: 10px 0;
	padding: 1px 100px 1px 10px;
	overflow: hidden;
	position: relative;
	background: #222222 top right no-repeat;
}
.oppdrag_list_uo h2 {
	border: none;
	margin: 10px 0;
	color: #EEEEEE;
	font-size: 13px;
	font-weight: bold;
	text-transform: uppercase;
}
.oppdrag_list_img {
	position: absolute;
	right: -10px;
	top: 0;
	margin: 0;
	padding: 0;
	opacity: 0.5;
}
');

// for å generere nødvendige params til nye oppdrag
if (false)
{
	$p = new params("20=4:name11:rank_points13=6:points3:01519=10:time_limit4:180048=5:prize38:13=4:cash5:1000019=11:rank_points3:500");
	#$p->update("chips", 250000);
	#$p->update("chips_start", 5000);
	$p->update("list_img", "http://i38.tinypic.com/mj3d76.png");
	dump($p->build());
}

// sjekk for nye oppdrag
login::$user->player->oppdrag->check_new();

/*echo '
<form action="" method="post">
	<p class="c">'.show_sbutton("Fjern alle mine oppdrag (testing)", 'name="delete_oppdrag"').' <a href="oppdrag?fiks" class="button">GI TILGANG TIL OPPDRAGENE</a></p>
</form>';
*/

echo '
<div class="bg1_c medium">
	<h1 class="bg1">Oppdrag<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="'.ess::$s['rpath'].'/node/26">Hjelp</a></p>
	<div class="bg1 r2b">';

// noen nye oppdrag?
if (count(login::$user->player->oppdrag->new) > 0)
{
	echo '
		<div class="information c"><p>Du har mottatt nye oppdrag.</p></div>';
}

// har vi noen oppdrag tilgjengelige?
if (count(login::$user->player->oppdrag->oppdrag) == 0)
{
	echo '
		<div class="information c"><p>Du har ingen oppdrag tilgjengelige.</p></div>
		<h3><b>Hvordan motta nye oppdrag?</b></h3>
		<p>Etter hvert som du stiger i høyere rang får du tilgang til nye og flere oppdrag. Har du nettopp utført et oppdrag, må du nok vente en stund før oppdraget blir tilgjengelig igjen.</p>
		<p>Sjekk derfor innom oppdrag av og til og se om du er klar for en utfordring!</p>';
}

else
{
	$i = 0;
	foreach (login::$user->player->oppdrag->oppdrag as $row)
	{
		// har vi bakgrunnsbilde?
		$bg = login::$user->player->oppdrag->params[$row['o_id']][$row['uo_locked'] != 0 ? 'o_unlock_params' : 'o_params']->get("list_img");
		$style = $bg ? ' style="background-image: url('.htmlspecialchars($bg).')"' : '';
		
		echo '
		<div class="oppdrag_list_uo r3"'.$style.'>
			<h2>'.htmlspecialchars($row['o_title']).'</h2>';
		
		// nytt oppdrag?
		if (isset(login::$user->player->oppdrag->new[$row['o_id']]))
		{
			echo '
			<p><b>Nytt oppdrag!</b></p>';
		}
		
		// ikke mulig å utføre enda?
		if ($row['uo_locked'] != 0)
		{
			echo '
			<div class="p">'.login::$user->player->oppdrag->get_description($row['o_id']).'</div>';
			
			// status
			echo login::$user->player->oppdrag->status($row['o_id']);
		}
		
		else
		{
			echo '
			<div class="p">'.login::$user->player->oppdrag->get_description($row['o_id']).'</div>';
			
			// akkurat forsøkt -- mislykte -- må vente
			if ($row['uo_last_state'] == 0 && $row['uo_last_time']+$row['o_retry_wait'] > time())
			{
				$wait = $row['uo_last_time']+$row['o_retry_wait'] - time();
				
				echo '
			<p>Du mislyktes oppdraget forrige gang du prøvde og må vente '.game::counter($wait, true).' før du kan prøve igjen.</p>';
			}
			
			else
			{
				echo '
			<p><a href="oppdrag?o_id='.$row['o_id'].'" class="button">Start oppdrag &raquo;</a></p>';
			}
		}
		
		echo '
		</div>';
	}
}

echo '
	</div>
</div>';

$_base->page->load();