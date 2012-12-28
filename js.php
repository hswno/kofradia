<?php

define("ALLOW_GUEST", true);
require "base.php";
global $_base;

$_base->page->theme_file = "guest";

echo '
<h1>Krever JavaScript støtte</h1>
<p>Hvis du har kommet hit ved å trykke på en link, og forventet at noe helt annet skulle skje, er det mest sannsynlig fordi din nettleser ikke har støtte for JavaScript.</p>
<p>For å kunne utnytte Kofradia fullt ut, må nettleseren din ha støtte for JavaScript. Se gjennom hjelpefilene for din nettleser eller last ned en nyere nettleser for å aktivere denne støtten.</p>
<p>Vi anbefaler bruk av <a href="http://getfirefox.com/">Firefox</a> eller <a href="http://www.opera.com/download/">Opera</a>.</p>';

$_base->page->load();