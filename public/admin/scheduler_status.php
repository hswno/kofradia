<?php

require "../base.php";
global $_base;

$_base->page->add_title("Planlegger", "Status");

echo '
<h1>Planlegger - Status</h1>';

$result = \Kofradia\DB::get()->query("SELECT s_name, s_hours, s_minutes, s_seconds, s_file, s_description, s_count, s_previous, s_next, s_active FROM scheduler ORDER BY s_name");

if ($result->rowCount() == 0)
{
	echo '
<p>
	Ingen rutiner er opprettet.
</p>';
}

else
{
	$i = 0;
	while ($row = $result->fetch())
	{
		$info = game::bb_to_html($row['s_description']);
		
		echo '
<div class="section w250'.($i++ % 2 == 0 && $i > 0 ? ' clear' : '').'" style="float: left; margin-left: 25px">
	<h2>'.htmlspecialchars($row['s_name']).($row['s_active'] == 0 ? ' <span class="dark">(inaktiv)</span>' : '').'</h2>
	<dl>
		<dt>Timer</dt>
		<dd class="r">'.htmlspecialchars($row['s_hours']).'</dd>
		
		<dt>Minutter</dt>
		<dd class="r">'.htmlspecialchars($row['s_minutes']).'</dd>
		
		<dt>Sekunder</dt>
		<dd class="r">'.htmlspecialchars($row['s_seconds']).'</dd>
		
		<dt>Scriptfil</dt>
		<dd class="r">'.htmlspecialchars($row['s_file']).'</dd>
		
		<dt>Antall ganger</dt>
		<dd class="r">'.game::format_number($row['s_count']).'</dd>
		
		<dt>Forrige</dt>
		<dd class="r">'.$_base->date->get($row['s_previous'])->format(date::FORMAT_SEC).'</dd>
		
		<dt>Neste</dt>
		<dd class="r">'.$_base->date->get($row['s_next'])->format(date::FORMAT_SEC).($row['s_next'] != 0 ? '<br />'.game::timespan($row['s_next'], game::TIME_ABS) : '').'</dd>
		
		<dt>Beskrivelse</dt>
		<dd class="r">'.(!empty($info) ? $info : 'Ingen informasjon.').'</dd>
	</dl>
</div>';
	}
}

$_base->page->load();