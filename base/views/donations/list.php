<?php

// data:
// \pagei  $pagei
// array(\Kofradia\Donation, ..)  $donations

?>
<div class="bg1_c xsmall">
	<h1 class="bg1">Donasjoner<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="donasjon">&laquo; Tilbake</a></p>

<?php
if ($pagei->total > 0)
{
	echo '
	<p class="h_right">Side '.$pagei->active.' av '.$pagei->pages.'</p>';
}

?>
	<div class="bg1">
		<p>Denne siden viser en komplett oversikt over alle som har donert og gitt sin støtte til Kofradia.</p>

<?php
if (!$donations)
{
	echo '
		<p>Ingen donasjoner er registrert.</p>';
}
else
{
	echo '
		<dl class="dd_right">';

	foreach ($donations as $donation)
	{
		$up_id = $donation->getPlayerID();
		$user = $up_id ? '<user id="'.$up_id.'" />' : 'Anonym';

		echo '
			<dt>'.$user.'</dt>
			<dd>'.\ess::$b->date->get($donation->getTime())->format(\date::FORMAT_NOTIME).'</dd>';
	}

	echo '
		</dl>';
}

?>
		<p class="c">Vil du også bidra? Trykk <a href="donasjon">her</a>!</p>
<?php
if ($pagei->pages > 1)
{
	echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
}
?>

	</div>
</div>