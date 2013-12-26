<?php

// data:
// int    $tos_version
// int    $tos_update
// string $tos
// \user  $user
// bool   $updated

echo '
<h1>Betingelser</h1>
<p>Dette er versjon '.$tos_version.' og har vært gjeldende siden '.\ess::$b->date->get($tos_update)->format(date::FORMAT_NOTIME).'.</p>';

if ($user)
{
	echo '
<p>';

	if ($updated)
	{
		echo 'Dette er første gang du viser denne versjonen av betingelsene.';
	}
	else
	{
		echo 'Du viste disse betingelsene for første gang '.\ess::$b->date->get($user->data['u_tos_accepted_time'])->format(date::FORMAT_NOTIME).'.';
	}

	echo '
	<b>Ditt videre bruk av tjenesten og nettsiden betyr at du samtykker til disse betingelsene.</b>
	Hvis du ikke samtykker, må du <a href="'.\ess::$s['rpath'].'/min_side?u&a=deact">avslutte din konto</a> og slutte å bruke tjenesten.
</p>
<p>Brudd på betingelsene kan føre til deaktivering og utestengelse.</p>';
}

echo '
<div id="betingelser_content">'.$tos.'</div>';