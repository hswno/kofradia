<?php

return array(
	// GitHub-stuff
	"github"         => "GitHub@index",
	"github-catchup" => "GitHub@updateSeenAndGotoGitHub",

	// polls
	"polls"             => "Polls@index",
	"polls/([0-9]+)"    => "Polls@index",
	"polls/vote"        => "Polls@vote",
	"polls/admin(/.*)?" => "Polls@admin",
);