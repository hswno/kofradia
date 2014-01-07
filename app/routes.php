<?php

return array(
	// index
	""               => "Misc@index",
	// polls
	"polls"             => "Polls@index",
	"polls/([0-9]+)"    => "Polls@index",
	"polls/vote"        => "Polls@vote",
	"polls/admin(/.*)?" => "Polls@admin",

	// div funksjoner
	"angrip"         => "Game\\Angrep@index",
	"auksjoner"      => "Game\\Auksjoner@index",
	"banken"         => "Game\\Banken@index",
	"bydeler"        => "Game\\Bydeler@index",
	"kriminalitet"   => "Game\\Kriminalitet@index",
	"lotto"          => "Game\\Lotto@index",
	"utpressing"     => "Game\\Utpressing@index",
	"utpressing/log" => "Game\\Utpressing@log",

	// diverse
	"betingelser"     => "Misc@betingelser",
	"credits"         => "Misc@credits",
	"donasjon"        => "Donations@index",
	"donasjon/notify" => "Donations@notify",
	"github"          => "GitHub@index",
	"min_side"        => "MinSide@index",

	"ranklist"     => "Game\\Ranklist@index",

	// statistikk
	"antall_online_top" => "Stats@online_top",

	// kontakter
	"kontakter"                 => "Users\\Contacts@list",
	"kontakter/add/([0-9]+)"    => "Users\\Contacts@add",
	"kontakter/edit/([0-9]+)"   => "Users\\Contacts@edit",
	"kontakter/delete"          => "Users\\Contacts@delete_many",
	"kontakter/delete/([0-9]+)" => "Users\\Contacts@delete",

	// autologin
	"autologin/([0-9a-z]+)" => "Users\\Autologin@index",

);