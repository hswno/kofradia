<?php

define("KORT_URL", STATIC_LINK . "/kort/60x90/%d/%s.png");
global $oppdrag, $trigger, $status, $expire, $_base;

/*
 * 			chips (int): Antall chips man skal nå
 * 			chips_start (int): Hvor mange chips man starter med
 * 			time_limit (int): Hvor lang tid man har
 * 			STATUS chips (int): Hvor mange chips man har nå
 * 			STATUS cards (text): Hvilke kort brukeren har
 * 			STATUS cards_pc (text): Hvilke kort pcen har
 * 			STATUS bet (int): Hvor mye penger som satses nå eller forrige gang
 * 			STATUS finish (int): Vise resultat?
 */

$chips = $status->get("chips");
$target = $trigger->get("chips");

// har ikke mange nok chips?
if ($chips <= 0)
{
	login::$user->player->oppdrag->failed($oppdrag['o_id'], 'Du hadde ingen flere chips igjen. Oppdraget &laquo;$name&raquo; ble mislykket.');
	$_base->page->add_message("Du hadde ingen flere chips igjen. Oppdraget ble mislykket.");
	redirect::handle("oppdrag");
}

// finn status
$cards = $status->get("cards");
$finish = $status->get("finish");

// starte nytt spill?
if (isset($_POST['chips']) && !$cards)
{
	$bet = game::intval($_POST['chips']);
	if ($bet > $chips)
	{
		$_base->page->add_message("Du har ikke så mange chips.", "error");
		redirect::handle();
	}
	
	if ($bet < min(100, $chips))
	{
		$_base->page->add_message("Du må minimum satse ".min(100, $chips)." chips.", "error");
		redirect::handle();
	}
	
	// start
	$poker = new CardsPoker();
	$poker->new_cards(5);
	$status->update("cards", implode(",", $poker->get_cards()));
	
	$poker_pc = new CardsPoker();
	$poker_pc->remove_cards($poker->get_cards());
	$poker_pc->new_cards(5);
	$status->update("cards_pc", implode(",", $poker_pc->get_cards()));
	
	$status->update("cards_used", implode(",", array_merge($poker->get_cards(), $poker_pc->get_cards())));
	$status->update("bet", $bet);
	
	// oppdater
	login::$user->player->oppdrag->update_status($oppdrag['o_id'], $status);
	redirect::handle();
}

// bytte kort?
if (isset($_POST['choose']) && $cards && !$finish)
{
	$poker = new CardsPoker(explode(",", $cards));
	$poker->remove_cards(explode(",", $status->get("cards_used")));
	
	// finn ut hvilke bilder som skal beholdes
	$replace = array(0,1,2,3,4);
	$arr = isset($_POST['kort']) && is_array($_POST['kort']) && count($_POST['kort']) <= 5 ? $_POST['kort'] : array();
	
	foreach ($arr as $value)
	{
		if (isset($replace[$value])) unset($replace[$value]);
	}
	
	// hent nye kort
	if (count($replace) > 0)
	{
		$poker->new_cards($replace);
	}
	
	$status->update("cards", implode(",", $poker->get_cards()));
	$status->update("cards_used", implode(",", array_unique(array_merge(explode(",", $status->get("cards_used")), $poker->get_cards()))));
	$status->update("finish", 1);
	
	login::$user->player->oppdrag->update_status($oppdrag['o_id'], $status);
	redirect::handle();
}

// ferdig?
$success = false;
if ($finish)
{
	$poker = new CardsPoker(explode(",", $cards));
	$poker_pc = new CardsPoker(explode(",", $status->get("cards_pc")));
	$poker_pc->remove_cards(explode(",", $status->get("cards_used")));;
	
	// spill for pcen
	$poker_pc->play();
	
	$solve = $poker->solve();
	$solve_pc = $poker_pc->solve();
	
	// hvem vant?
	$won = CardsPoker::compare($solve, $solve_pc);
	if ($won[0] == 1)
	{
		// brukeren vant -- gi tilbake nye chips
		$chips = $chips+$status->get("bet");
	}
	elseif ($won[0] == 2)
	{
		// pcen vant -- trekk fra chips
		$chips = $chips-$status->get("bet");
	}
	
	$status->update("chips", $chips);
	$cards = false;
	
	$status->remove("cards");
	$status->remove("cards_pc");
	$status->remove("finish");
	$status->remove("cards_used");
	login::$user->player->oppdrag->update_status($oppdrag['o_id'], $status);
	
	// ingen flere chips?
	if ($chips <= 0)
	{
		$cards = true;
		login::$user->player->oppdrag->failed($oppdrag['o_id'], 'Du hadde ingen flere chips igjen. Oppdraget &laquo;$name&raquo; ble mislykket.');
		//$_base->page->add_message("Du har ingen flere chips igjen. Oppdraget ble mislykket.");
	}
	
	// nådd målet?
	elseif ($chips >= $target)
	{
		$time_limit = $trigger->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
		login::$user->player->oppdrag->success($oppdrag['o_id'], 'Du klarte å spille deg opp til '.game::format_number($chips).' chips innen det hadde gått '.game::timespan($time_limit, game::TIME_FULL).'. Du nådde målet på '.game::format_number($target).' chips. Oppdraget &laquo;$name&raquo; ble vellykket!');
		$success = true;
	}
}


$_base->page->add_js('sm_scripts.poker_parse();');

// progress for tid
$progress_time_limit = (int) $trigger->get("time_limit", oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);
$progress_time_status = time() - $oppdrag['uo_active_time'];
$progress_time = $progress_time_status / $progress_time_limit * 100;

// javascript for progress for tiden
$_base->page->add_js_domready('
	new CountdownProgressbarTime($("progress_time"), '.$progress_time_status.', '.$progress_time_limit.');');

echo '
<div class="bg1_c small bg1_padding" style="width: 420px">
	<h1 class="bg1">'.htmlspecialchars($oppdrag['o_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="oppdrag?force">&laquo; Tilbake</a></p>
	<div class="bg1">'.(!$success ? '
		<p>Du må ha mer enn <b>'.game::format_number($target).'</b> chips om <u>'.game::counter($expire-time()).'</u>.<br />
		Du har nå <b>'.game::format_number($chips).'</b> chips'.($chips < $target ? ' og mangler '.game::format_number($target-$chips).' chips' : '').'.</p>' : '
		<p>Trykk <a href="oppdrag">her</a> for å gå tilbake til oppdrag.</p>').'
		<div class="progressbar">
			<div class="progress" style="width: '.round(min($chips, $target)/$target * 100).'%"><p>Du har '.game::format_number(min($chips, $target)/$target * 100, 1).' % av antall chips du trenger</p></div>
		</div>
		<div class="progressbar" style="margin-top: 3px">
			<div class="progress" style="width: '.round($progress_time).'%" id="progress_time"><p>'.game::timespan($progress_time_limit-$progress_time_status, game::TIME_FULL).' gjenstår</p></div>
		</div>';

// nytt spill?
if (!$cards && !$success)
{
	ess::$b->page->add_js_domready('$("chips").focus();');
	echo '
		<div class="bg1_c" style="width: 65%">
			<h2 class="bg1">Nytt spill<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1 c">
				<form action="" method="post">
					<p>Antall chips: <input type="text" class="styled w80 r" name="chips" id="chips" value="'.game::format_number(min($chips, $status->get("bet", 1000))).' chips" /> '.show_sbutton("Start").'</p>
				</form>
			</div>
		</div>';
}

// velge kort?
if ($cards && !$finish)
{
	echo '
		<div class="bg1_c">
			<h2 class="bg1">Velg kort<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1 c">
				<form action="" method="post">
					<p>Spiller om: <b>'.game::format_number($status->get("bet")).'</b> chips</p>
					<p>Marker de kortene du ønsker å <u>beholde</u>.</p>
					<p>';
	
	$poker = new CardsPoker(explode(",", $cards));
	$solve = $poker->solve();
	if ($solve[0] == 0) $solve[2] = array($solve[3][0] => true);
	foreach ($poker->active as $key => $card)
	{
		echo sprintf('
						<input type="checkbox" name="kort[]" value="%d" id="kort%d"%s /><label for="kort%d"><img src="%s" alt="%s" title="%s" class="spillekort" /></label>',
			$key, $key,
			(isset($solve[2][$key]) ? /*' checked="checked"'*/'' : ''),
			$key,
			htmlspecialchars(sprintf(KORT_URL,
				$card->num+1, $card->group['name']
			)),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign()
		);
	}
	
	$text = $poker->solve_text($solve);
	
	echo '
					</p>
					<p>'.$text.'</p>
					<p>'.show_sbutton("Fortsett", 'name="choose"').'</p>
				</form> 
			</div>
		</div>';
}

// vise resultat?
if ($finish)
{
	echo '
		<div class="bg1_c">
			<h2 class="bg1">Resultat<span class="left2"></span><span class="right2"></span></h2>
			<div class="bg1 c">
				<div class="information">';
	
	switch ($won[0])
	{
		case 0:
			echo '
					<p>Runden ble uavgjort.</p>';
		break;
		
		case 1:
			if ($won[1])
			{
				echo '
					<p>Dere fikk samme kombinasjon, men du hadde høyere highcard og <u>vant</u> <b>'.game::format_number($status->get("bet")).'</b> chips.</p>';
				break;
			}
			echo '
					<p>Du fikk bedre kombinasjon enn motstanderen og <u>vant</u> <b>'.game::format_number($status->get("bet")).'</b> chips.</p>';
		break;
		
		case 2:
			if ($won[1])
			{
				echo '
					<p>Dere fikk samme kombinasjon, men motstanderen din hadde høyere highcard. Du <u>tapte</u> <b>'.game::format_number($status->get("bet")).'</b> chips.</p>';
				break;
			}
			echo '
					<p>Motstanderen fikk bedre kombinasjon enn deg. Du <u>tapte</u> <b>'.game::format_number($status->get("bet")).'</b> chips.</p>';
	}
	
	echo '
				</div>'.($chips <= 0 ? '
				<div class="warning">
					<p>Du har ingen flere chips igjen. Oppdraget ble mislykket.</p>
				</div>' : ($success ? '
				<div class="information">
					<p>Du klarte å spille deg opp til '.game::format_number($chips).' chips og nådde derfor målet på '.game::format_number($target).' chips. Oppdraget ble vellykket!</p>
				</div>' : '')).'
				<p><b>Dine kort:</b><br />'.$poker->solve_text($solve).'</p>
				<p>';
	
	foreach ($poker->active as $key => $card)
	{
		echo sprintf('
					<img src="%s" alt="%s" title="%s" class="spillekort%s" />',
			htmlspecialchars(sprintf(KORT_URL,
				$card->num+1, $card->group['name']
			)),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
			(isset($solve[2][$key]) ? ' result' : ' noresult')
		);
	}
	
	echo '
				</p>
				<p><b>Motstanderens kort:</b><br />'.$poker_pc->solve_text($solve_pc).'</p>
				<p>';
	
	foreach ($poker_pc->active as $key => $card)
	{
		echo sprintf('
					<img src="%s" alt="%s" title="%s" class="spillekort%s" />',
			htmlspecialchars(sprintf(KORT_URL,
				$card->num+1, $card->group['name']
			)),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
			ucfirst(htmlspecialchars($card->group['title'])).' '.$card->sign(),
			(isset($solve_pc[2][$key]) ? ' result' : ' noresult')
		);
	}
	
	echo '
				</p>
			</div>
		</div>';
}

echo '
	</div>
</div>';