<?php

require "base/essentials.php";

// sjekk at dette er GitHub
if (!\Kofradia\Network\Helpers::cidr_match($_SERVER['REMOTE_ADDR'], "192.30.252.0/22") || !isset($_POST['payload'])) {
	//putlog("CREWCHAN", "%bgithub invalid request%b");
	die("Bye, bitch!");
}

class github_handle
{
	private $event;
	private $payload;

	public function __construct() {
		$this->event = $_SERVER['HTTP_X_GITHUB_EVENT'];
		$this->payload = json_decode($_POST['payload'], true);
		$this->handle_event($this->event);
	}

	private function info($title, $text) {
		putlog("CREWCHAN", "%bGitHub - $title%b: $text");
	}

	private function get_action($action, $actions) {
		if (!isset($actions[$action])) {
			return "unknown: $action";
		}

		return $actions[$action];
	}

	public function handle_event() {
		switch ($this->event) {
			case "push":
				foreach ($this->payload['commits'] as $commit) {
					$msg = sprintf("%%u%s%%u pushet kode til %s (%s) (%s): %s",
						$commit['author']['name'] ?: $commit['author']['email'],
						$this->payload['repository']['name'],
						$this->payload['ref'],
						$commit['url'],
						$commit['message']);
					$this->info("Kildekode", $msg);
				}
				break;

			case "issues":
				// issues - closed, opened, reopened
				$types = array("closed" => "lukket", "opened" => "opprettet", "reopened" => "gjenåpnet");
				$msg = sprintf("%s %s issue #%d (%s) %s",
					$this->payload['sender']['login'],
					$types[$this->payload['action']],
					$this->payload['issue']['number'],
					$this->payload['issue']['title'],
					$this->payload['issue']['html_url']);
				$this->info("Issue {$payload['action']}", $msg);
				break;

			case "issue_comment":
				$msg = sprintf("%%u%s%%u svarte på issue #%d (%s) %s",
					$this->payload['sender']['login'],
					$this->payload['issue']['number'],
					$this->payload['issue']['title'],
					$this->payload['comment']['html_url']);
				$this->info("Issue", $msg);
				break;

			//case "commit_comment":
				//TODO: commit_comment
				////break;

			//case "create":
				//TODO: create
				//break;

			//case "delete":
				//TODO: delete
				//break;

			//case "pull_request":
				//TODO: pull_request
				//break;

			//case "pull_request_review_comment":
				//TODO: pull_request_review_comment
				//break;

			case "gollum":
				foreach ($this->payload['pages'] as $page) {
					$action = $this->get_action($page['action'], array("edited" => "oppdaterte", "created" => "opprettet"));
					$msg = sprintf("%%u%s%%u %s %s %s",
						$this->payload['sender']['login'],
						$action,
						$page['title'],
						$page['html_url']);
					$this->info("Wiki", $msg);
				}
				break;

			//case "watch":
				//TODO: watch
				//break;

			//case "release":
				//TODO: release
				//break;

			//case "fork":
				//TODO: fork
				//break;

			//case "member":
				//TODO: member
				//break;

			//case "public":
				//TODO: public
				//break;

			//case "team_add":
				//TODO: team_add
				//break;

			//case "status":
				//TODO: status
				//break;

			default:
				putlog("CREWCHAN", "%bukjent github event:%b {$this->event}");
				if (MAIN_SERVER) {
					$data = sprintf("%s\nevent: %s\npayload:\n%s\n\n",
						date("r"),
						$this->event,
						print_r($this->payload, true));
					file_put_contents("../github.log", $data, FILE_APPEND);
				}
		}
	}
}

new github_handle();