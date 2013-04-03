<?php

require "../../base.php";
global $_base;

$_base->page->add_title("Bankoverføringer - Sammenlikne spillere");

// init
$player1 = false;
$player2 = false;
$player_1 = "";
$player_2 = "";
$where = false;

// skal vi sammenlikne nå?
if (isset($_GET['u1']))
{
	$player_1 = (int) getval("u1");
	$player_2 = (int) getval("u2");
	
	// sjekk at player1 finnes
	$result = $_base->db->query("SELECT up_id, up_name FROM users_players WHERE up_id = $player_1");
	if (!($player1 = mysql_fetch_assoc($result)))
	{
		$_base->page->add_message("Spilleren med ID <u>".$player_1."</u> finnes ikke!", "error");
	}
	else
	{
		if (!empty($player_2))
		{
			$result = $_base->db->query("SELECT up_id, up_name FROM users_players WHERE up_id = $player_2");
			if (!($player2 = mysql_fetch_assoc($result)))
			{
				$_base->page->add_message("Spilleren med ID <u>".htmlspecialchars($player_2)."</u> finnes ikke!", "error");
				$player1 = false;
			}
			
			else
			{
				$where = "(bl_sender_up_id = {$player1['up_id']} AND bl_receiver_up_id = {$player2['up_id']}) OR (bl_sender_up_id = {$player2['up_id']} AND bl_receiver_up_id = {$player1['up_id']})";
			}
		}
		else
		{
			$where = "(bl_sender_up_id = {$player1['up_id']} OR bl_receiver_up_id = {$player1['up_id']})";
		}
	}
}


echo '
<h1>Bankoverføringer</h1>
<form action="" method="get">
	<table class="table center tablemb">
		<tbody>
			<tr>
				<th>Spiller 1 (ID)</th>
				<td><input type="text" name="u1" class="styled w80" value="'.($player_1 ? $player_1 : "").'" /></td>
			</tr>
			<tr>
				<th>Spiller 2 (ID)</th>
				<td><input type="text" name="u2" class="styled w80" value="'.($player_2 ? $player_2 : "").'" /></td>
			</tr>
			<tr>
				<th colspan="2" style="text-align: center">'.show_sbutton("Vis overføringer").'</th>
			</tr>
		</tbody>
	</table>
</form>';


if ($where)
{
	$result = $_base->db->query("SELECT id, bl_sender_up_id, bl_receiver_up_id, amount, time FROM bank_log WHERE $where ORDER BY id DESC");
	
	echo '
<table class="table center tablemb">
	<tbody>
		<tr>
			<th>Spiller 1</td>
			<td>'.$player1['up_id'].'</td>
			<td><user id="'.$player1['up_id'].'" /></td>
		</tr>';
	
	if ($player2)
	{
		echo '
		<tr>
			<th>Spiller 2</td>
			<td>'.$player2['up_id'].'</td>
			<td><user id="'.$player2['up_id'].'" /></td>
		</tr>';
	}
	
	echo '
	</tbody>
</table>
<table class="table center">
	<thead>
		<tr>
			<td>ID</td>
			<td>Sender</td>
			<td>Mottaker</td>
			<td>Beløp</td>
			<td>Dato</td>
		</tr>
	</thead>
	<tbody>';
	
	if (mysql_num_rows($result) == 0)
	{
		echo '
		<tr>
			<td colspan="5">Ingen overføringer</td>
		</tr>';
	}
	
	else
	{
		while ($row = mysql_fetch_assoc($result))
		{
			echo '
		<tr>
			<td>'.$row['id'].'</td>
			<td><user id="'.$row['bl_sender_up_id'].'" /></td>
			<td><user id="'.$row['bl_receiver_up_id'].'" /></td>
			<td>'.game::format_cash($row['amount']).'</td>
			<td>'.$_base->date->get($row['time'])->format(date::FORMAT_SEC).'</td>
		</tr>';
		}
	}
	
	echo '
	</tbody>
</table>';
}

$_base->page->load();