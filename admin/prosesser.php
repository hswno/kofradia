<?php

require "../base.php";
global $_base;

access::need("admin");
$_base->page->add_title("Prosesser som kjører på serveren");

#$result = shell_exec('ps aux|grep httpd|grep -v SCREEN|grep -v grep|grep -v ./40*|grep -v "ps aux"');
$result = shell_exec('ps aux');
$linjer = explode("\n", str_replace("\r", "", $result));

$prosesser = array();
$i = 0;
$keys = array();
foreach ($linjer as $linje)
{
	if (empty($linje)) continue;
	$linje = preg_split("/[ \t]+/u", $linje, 11);
	if ($linje[1] == "PID") continue;
	$prosesser[] = $linje;
	$keys[$i] = floatval($linje[2]);
	$i++;
}	

array_multisort($keys, SORT_DESC, $prosesser);

echo '
<table class="table game tablemb" style="width: 100%">
	<thead>
		<tr>
			<th colspan="11">Prosesser ('.count($prosesser).' oppføring'.(count($prosesser) == 1 ? '' : 'er').')</th>
		</tr>
		<tr>
			<td>USER</td>
			<td>PID</td>
			<td>% CPU</td>
			<td>% MEM</td>
			<td>VSZ</td>
			<td>RSS</td>
			<td>TTY</td>
			<td>STAT</td>
			<td>START</td>
			<td>TIME</td>
			<td>COMMAND</td>
		</tr>
	</thead>
	<tbody>';

$total_cpu = 0;
$total_mem = 0;
$i = 0;
foreach ($prosesser as $info)
{
	$total_cpu += $info[2];
	$total_mem += $info[3];
	echo '
		<tr'.(is_int(++$i/2) ? ' class="color"' : '').'>
			<td>'.$info[0].'</td>
			<td>'.$info[1].'</td>
			<td><b>'.$info[2].'</b></td>
			<td>'.$info[3].'</td>
			<td>'.$info[4].'</td>
			<td>'.$info[5].'</td>
			<td>'.$info[6].'</td>
			<td>'.$info[7].'</td>
			<td>'.$info[8].'</td>
			<td>'.$info[9].'</td>
			<td>'.htmlspecialchars($info[10]).'</td>
		</tr>';
}

echo '
		<!--<tr'.(is_int(++$i/2) ? ' class="color"' : '').'>-->
		<tr class="resultat">
			<td colspan="2">&nbsp;</td>
			<td>'.$total_cpu.'%</td>
			<td>'.$total_mem.'%</td>
			<td colspan="7">&nbsp;</td>
		</tr>
	</tbody>
</table>';

$_base->page->theme_file = "doc";
$_base->page->load();