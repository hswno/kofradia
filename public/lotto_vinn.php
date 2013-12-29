<?php

require "base.php";
global $_base;

$_base->page->add_title("Lotto", "Vinn");

// hvem skal vi vise oversikten over?
$up_id = login::$user->player->id;
if (isset($_GET['up_id']) && access::has("mod"))
{
	$find = (int) getval("up_id");
	$result = \Kofradia\DB::get()->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_id = $find");
	if ($result->rowCount() == 0)
	{
		$_base->page->add_message("Fant ingen spiller med ID <b>".htmlspecialchars($_GET['up_id'])."</b>!", "error");
	}
	else
	{
		$row = $result->fetch();
		$up_id = $row['up_id'];
		$_base->page->add_message("Du viser lottoresultatene for spilleren ".game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'])."!");
	}
}


echo '
<div class="bg1_c medium">
	<h1 class="bg1">Lotto: Historie<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="lotto">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p class="c">Her er en oversikt over alle gangene du har vunnet på lotto!</p>';


// antall vinn og totalt vunnet
$result = \Kofradia\DB::get()->query("SELECT COUNT(id), SUM(won) FROM lotto_vinnere WHERE lv_up_id = $up_id");
$row = $result->fetch(\PDO::FETCH_NUM);
$ant = $row[0];
$won = $row[1];

if ($ant == 0)
{
	echo '
		<p class="c"><b>Du har aldri vunnet på lotto!</b></p>';
}

else
{
	$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 15);
	$result = $pagei->query("SELECT l_id, lv_up_id, time, won, total_lodd, total_users, type FROM lotto_vinnere WHERE lv_up_id = $up_id ORDER BY id DESC");
	
	echo '
		<p class="c">Du har vunnet i lotto <b>'.game::format_number($ant).'</b> gang'.($ant == 1 ? '' : 'er').'! Totalt har du vunnet <b>'.game::format_cash($won).'</b>!</p>
		<table class="table center game" id="lotto_vinn" width="100%">
			<thead>
				<tr>
					<th>Når</th>
					<th>Plassering</th>
					<th>Premie</th>
					<th>Vinnerlodd</th>
					<th>Solgte lodd</th>
					<th>Spillere</th>
				</tr>
			</thead>
			<tbody>';
	
	$i = 0;
	while ($row = $result->fetch())
	{
		$end = ceil(($row['time']-900)/1800)*1800 + 900;
		echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td class="c">'.$_base->date->get($end)->format().'<br /><span style="color: #888888">('.$_base->date->get($row['time'])->format().')</a></td>
					<td class="r">'.($row['type'] == 1 ? '<b>'.$row['type'].'. plass</b>' : $row['type'].'. plass').'</td>
					<td class="r">'.game::format_cash($row['won']).'</td>
					<td class="c">'.game::format_number($row['l_id']).'</td>
					<td class="c">'.game::format_number($row['total_lodd']).'</td>
					<td class="c">'.game::format_number($row['total_users']).'</td>
				</tr>';
	}
	
	echo '
			</tbody>
		</table>
		<p class="c">'.$pagei->pagenumbers(game::address("lotto_vinn", $_GET, array("side"))."#lotto_vinn", game::address("lotto_vinn", $_GET, array("side"), array("side" => "_pageid_"))."#lotto_vinn").'</p>';
}

echo '
	</div>
</div>';

$_base->page->load();