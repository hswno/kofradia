<?php

require "../base.php";
global $_base;

access::need_nostat();
$_base->page->add_title("Penger");


// gi oss selv penger?
if (isset($_POST['cash']))
{
	$cash = game::intval($_POST['cash']);
	
	$result = $_base->db->query("SELECT $cash > 10000000000");
	if (mysql_result($result, 0))
	{
		$_base->page->add_message("Du kan ikke sette pengene dine til over 10 mrd!", "error");
	}
	
	else
	{
		$_base->db->query("UPDATE users_players SET up_cash = $cash, up_bank = 0 WHERE up_id = ".login::$user->player->id." AND $cash <= 10000000000");
		$_base->page->add_message("Du har nå nøyaktig <b>".game::format_cash($cash)."</b> på hånda og <b>0 kr</b> i banken!");
		
		putlog("LOG", "%b%c8MODERATOR PENGER:%c%b %u".login::$user->player->data['up_name']."%u endret penene sine til nøyaktig %u".game::format_cash($cash)."%u. Tidligere kontant: ".game::format_cash(login::$user->player->data['up_cash']).". Tidligere i banken: ".game::format_cash(login::$user->player->data['up_bank']).".");
	}
	redirect::handle();
}


// har vi over 1 bill nå?
$result = $_base->db->query("SELECT ".login::$user->player->data['up_cash']."+".login::$user->player->data['up_bank']." > 10000000000, ".login::$user->player->data['up_cash']."+".login::$user->player->data['up_bank'].", 100000000-".login::$user->player->data['up_cash']."-".login::$user->player->data['up_bank']);
$over = mysql_result($result, 0);
$cash = mysql_result($result, 0, 1);
$igjen = mysql_result($result, 0, 2);

echo '
<h1>Penger</h1>
<p>
	Som en moderator kan du nå gi deg selv penger, og kan maksimalt ha 10 mrd ved hjelp av denne funksjonen. Ønsker du mer må du spille deg opp på for eksempel pokerfunksjonen.
</p>';


/*if ($over)
{
	echo '
<p>
	Du har <b>'.game::format_cash($cash).'</b> noe som er over 1 bill! '.game::smileys(";)").'
</p>';
}


else*/
{
	echo '
<form action="" method="post">
	<p>
		Du har <b>'.game::format_cash($cash).'</b>.
	</p>
	<p>
		Når du bruker denne funksjonen vil du ende opp med så mye penger du spesifiserer nedenfor.
	</p>
	<table class="table tablemb">
		<tr>
			<th>Jeg vil ha totalt kr</th>
			<td><input type="text" name="cash" value="'.($over ? game::format_cash(10000000000) : game::format_cash($cash)).'" class="styled w100" /></td>
		</tr>
		<tr>
			<th colspan="2" class="c">'.show_sbutton("Gi meg pengene!").'</th>
		</tr>
	</table>
</form>';
}

$_base->page->load();