<?php

require "base.php";
global $_base;

$kort_path = STATIC_LINK . "/kort/60x90";

// legg til javascript
$_base->page->add_js('sm_scripts.poker_parse();');

echo '
<div class="bg1_c">
	<h1 class="bg1">Pokerkort<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Dette er et eksempel på pokerkort.</p>';

$e = 0;
foreach (array("klover", "spar", "ruter", "hjerter") as $farge)
{
	echo '
		<p style="overflow: hidden">';
	
	for ($i = 2; $i <= 14; $i++, $e++)
	{
		echo '
			<input type="checkbox" name="kort['.$e.']" id="kort'.$e.'" />
			<label for="kort0"><img src="'.$kort_path.'/'.$i.'/'.$farge.'.png" alt="'.ucfirst($farge).' '.$i.'" class="spillekort" /></label>';
	}
	
	echo '
		</p>';
}

echo '
		<p>test</p>
	</div>
</div>';

$_base->page->load();