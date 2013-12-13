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
	putlog("CREWCHAN", "%bgithub invalid request%b");
	die("Bye, bitch!");
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'];
$payload = json_decode($_POST['payload']);

putlog("CREWCHAN", "%bgithub event:%b $event");

function github_info($title, $text, $payload) {
	putlog("CREWCHAN", "%bGitHub $title%b: $text");
}

switch ($event) {
	case "push":
		//TODO: push
		break;

	case "issues":
		// issues - closed, opened, reopened
		$types = array("closed" => "lukket", "opened" => "opprettet", "reopened" => "gjenåpnet");
		github_info("Issue {$payload['action']}", "{$payload['sender']['login']} {$types[$payload['action']]} issuen #{$payload['issue']['number']} ({$payload['issue']['title']}) {$payload['issue']['html_url']}");
		break;

	case "issue_comment":
		github_info("Issue", "%u{$payload['sender']['login']}%u svarte på issuen #{$payload['issue']['number']} ({$payload['issue']['title']}) {$payload['comment']['html_url']}");
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
