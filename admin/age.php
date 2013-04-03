<?php

require "../base.php";
global $_base;

$_base->page->add_title("Beregn alder");

$age = false;
if (isset($_POST['birth']))
{
	$birth = postval("birth");
	if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])/u", $birth))
	{
		$_base->page->add_message("Ugyldig format.", "error");
	}
	
	else
	{
		$birth = explode("-", $birth);
		
		$date = $_base->date->get();
		$n_day = $date->format("j");
		$n_month = $date->format("n");
		$n_year = $date->format("Y");
		
		$age = $n_year - $birth[0] - (($n_month < $birth[1] || ($birth[1] == $n_month && $n_day < $birth[2])) ? 1 : 0);
	}
}

echo '
<h1>Beregn alder</h1>
<div class="section center w200">
	<h2>Beregn alder</h2>
	<form action="" method="post">
		<dl class="dd_right">
			<dt>Fødselsdato</dt>
			<dd><input type="text" name="birth" value="'.htmlspecialchars(postval("birth", '0000-00-00')).'" class="styled w80" /></dd>'.($age !== false ? '
			<dt>'.$_POST['birth'].' gir</dt>
			<dd>'.$age.' år</dd>' : '').'
		</dl>
		<p class="c">'.show_sbutton("Beregn alder").'</p>
	</form>
</div>';

$_base->page->load();