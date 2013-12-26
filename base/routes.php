<?php

return array(
	// index
	""               => "Misc@index",

	// GitHub-stuff
	"github"         => "GitHub@index",
	"github-catchup" => "GitHub@updateSeenAndGotoGitHub",

	// polls
	"polls"             => "Polls@index",
	"polls/([0-9]+)"    => "Polls@index",
	"polls/vote"        => "Polls@vote",
	"polls/admin(/.*)?" => "Polls@admin",

	// div funksjoner
	"angrip"        => "Game\\Angrep@index",
	"auksjoner"     => "Game\\Auksjoner@index",
	"banken"        => "Game\\Banken@index",
	"bydeler"       => "Game\\Bydeler@index",
	"kriminalitet"  => "Game\\Kriminalitet@index",
	"lotto"         => "Game\\Lotto@index",
	"utpressing"    => "Game\\Utpressing@index",

	// diverse
	"betingelser"  => "Misc@betingelser",
	"credits"      => "Misc@credits",
	"donasjon"     => "Donations@index",
	"min_side"     => "MinSide@index",

	"ranklist"     => "Game\\Ranklist@index",

	// statistikk
	"antall_online_top" => "Stats@online_top",
);