<?php

require "base/essentials.php";

function github_cidr_match($ip, $range)
{
	list ($subnet, $bits) = explode('/', $range);
	$ip = ip2long($ip);
	$subnet = ip2long($subnet);
	$mask = -1 << (32 - $bits);
	$subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
	return ($ip & $mask) == $subnet;
}

// sjekk at dette er GitHub
if (!github_cidr_match($_SERVER['REMOTE_ADDR'], "192.30.252.0/22") || !isset($_POST['payload'])) {
	die("Bye, bitch!");
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
$payload = json_decode($_POST['payload']);

switch ($event) {
	case "push":
		//TODO: push
		break;

	case "issues":
		//TODO: issues
		break;

	case "issue_comment":
		putlog("CREWCHAN", "%bGitHub Kommentar:%b %u{$payload['sender']['login']}%u svarte på kommentaren #{$payload['issue']['number']} ({$payload['issue']['title']}) {$payload['comment']['html_url']}");
		break;

	case "commit_comment":
		//TODO: commit_comment
		break;

	case "create":
		//TODO: create
		break;

	case "delete":
		//TODO: delete
		break;

	case "pull_request":
		//TODO: pull_request
		break;

	case "pull_request_review_comment":
		//TODO: pull_request_review_comment
		break;

	case "gollum":
		//TODO: gollum
		break;

	case "watch":
		//TODO: watch
		break;

	case "release":
		//TODO: release
		break;

	case "fork":
		//TODO: fork
		break;

	case "member":
		//TODO: member
		break;

	case "public":
		//TODO: public
		break;

	case "team_add":
		//TODO: team_add
		break;

	case "status":
		//TODO: status
		break;

}