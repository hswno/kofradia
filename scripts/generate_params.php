<?php

require "../base.php";
global $_base;

access::no_guest();
$_base->page->add_title("Generer params");

$count = 0;
$params = new params();
if (isset($_POST['name']) && is_array($_POST['name']))
{
	$params->add_text(postval("empty"));
	foreach ($_POST['name'] as $key => $row)
	{
		if (empty($row)) continue;
		$params->update($row, $_POST['value'][$key]);
	}
	
	$count = count($params->params);
}

echo '
<div class="bg1_c small">
	<h1 class="bg1">Generer params<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Her kan du generere params for å bruke i databasen osv.</p>'.($params ? '
		<p>Resultat:</p>
		<p><input type="text" value="'.htmlspecialchars($params->build()).'" class="styled w300" /></p>' : '').'
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Base:</dt>
				<dd><input type=text" name="empty" class="styled w200" /></dd>';

$list = array();
foreach ($params->params as $name => $value)
{
	$list[] = array($name, $value);
}

for ($i = 0; $i < max(5, $count+2); $i++)
{
	echo '
				<dt><input type="text" name="name['.$i.']" class="styled w100" value="'.(isset($list[$i]) ? htmlspecialchars($list[$i][0]) : '').'" /></dt>
				<dd><input type="text" name="value['.$i.']" class="styled w200" value="'.(isset($list[$i]) ? htmlspecialchars($list[$i][1]) : '').'" /></dt>';
}

echo '
			</dl>
			<p class="c">'.show_sbutton("Generer").'</p>
		</form>
	</div>
</div>';

$_base->page->load();