<?php

require "../base.php";
global $_base;

access::need("admin");
access::need_nostat();
$_base->page->add_title("Skaff ressurser!");

if (isset($_POST['rankpoeng']))
{
	$points = intval($_POST['rankpoeng']);
	if ($points != 0)
	{
		login::$user->player->increase_rank($points);
		$_base->page->add_message("Ranken din ble endret med <b>".game::format_number($points)."</b> rankpoeng.");
	}
}

if (isset($_POST['rankpoeng_abs']))
{
	$points = intval($_POST['rankpoeng_abs']);
	if ($points >= 0)
	{
		$points = $points - login::$user->player->data['up_points'];
		login::$user->player->increase_rank($points);
		$_base->page->add_message("Ranken din ble endret med <b>".game::format_number($points)."</b> rankpoeng.");
	}
}

if ($_SERVER['REQUEST_METHOD'] == "POST") redirect::handle();

echo '
<h1>Skaff ressurser</h1>

<form aciont="" method="post">
	<table class="table center tablemb">
		<tbody>
			<tr>
				<th>Rank</th>
				<td><input type="text" name="rankpoeng" class="styled w80" value="0" /></td>
			</tr>
			<tr>
				<th>Bestem rankpoeng</th>
				<td><input type="test" name="rankpoeng_abs" class="styled w80" value="-1" /></td>
			</tr>
			<tr>
				<th colspan="2" style="text-align: center">'.show_sbutton("Utfør!").'</th>
			</tr>
		</tbody>
	</table>
</form>';

$_base->page->load();