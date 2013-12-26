<?php

require "../../app/ajax.php";
ajax::require_user();

// sjekk handling
if (!isset($_POST['a1']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}
$action = $_POST['a1'];

// sjekk action
switch ($action)
{
	// bydeler
	/*case "bydeler":
		require "global/bydeler";
		break;*/
}

ajax::text("Ukjent handling.", ajax::TYPE_INVALID);