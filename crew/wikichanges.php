<?php

require "config.php";

// hvilke oppføringer skal vi hente fra?
$last = login::$user->params->get("wiki_last_seen");

if (isset(game::$settings['wiki_last_changed']))
{
	// oppdater bruker
	$d = ess::$b->date->get();
	$t = new DateTimeZone("UTC");
	$d->setTimezone($t);
	login::$user->params->update("wiki_last_seen", $d->format("YmdHis"));
	login::$user->params->update("wiki_last_changed", game::$settings['wiki_last_changed']['value'], true);
}

// send til korrekt side
$p = $last ? "&from=".$last : "";
redirect::handle("https://kofradia.no/crewstuff/wiki/w/Spesial:Siste_endringer?limit=500$p", redirect::ABSOLUTE);