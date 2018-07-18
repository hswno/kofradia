<?php namespace Kofradia\Controller\Game;

use \Kofradia\View;

class Oppdrag extends \Kofradia\Controller\Game {
	public $createResponseObject = false;


	public function action_index() {
		$this->needUser();

		\ess::$b->page->add_title("Oppdrag");

		$this->user->player->fengsel_require_no();
		$this->user->player->bomberom_require_no();

		// er vi på et aktivt oppdrag?
		if ($this->user->player->oppdrag->active) {
			return $this->action_active_oppdrag();
		}

		// hent alle oppdragene
		$this->user->player->oppdrag->user_load_all();

		// starte på et nytt oppdrag
		if (isset($_GET['o_id'])) {
			return $this->action_start_oppdrag();
		}

		\ess::$b->page->add_css('
.oppdrag_list_uo {
	margin: 10px 0;
	padding: 1px 100px 1px 10px;
	overflow: hidden;
	position: relative;
	background: #222222 top right no-repeat;
}
.oppdrag_list_uo h2 {
	border: none;
	margin: 10px 0;
	color: #EEEEEE;
	font-size: 13px;
	font-weight: bold;
	text-transform: uppercase;
}
.oppdrag_list_img {
	position: absolute;
	right: -10px;
	top: 0;
	margin: 0;
	padding: 0;
	opacity: 0.5;
}
');

		return View::forgeTwig("game/oppdrag/info", array(
			"oppdrag_info" => $this->user->player->oppdrag->get_oppdrag_info(),
			"oppdrag_new" => $this->user->player->oppdrag->new
		));
	}

	public function action_active_oppdrag() {

		$oppdrag = $this->user->player->oppdrag->active;
		$trigger = $this->user->player->oppdrag->params[$oppdrag['o_id']]['o_params'];
		$status = $this->user->player->oppdrag->params[$oppdrag['o_id']]['uo_params'];
		$expire = $oppdrag['uo_active_time']+$trigger->get("time_limit", \oppdrag::DEFAULT_TIME_LIMIT_ACTIVE);

		// tittel
		\ess::$b->page->add_title($oppdrag['o_title']);

		// avbryte oppdraget?
		if (isset($_POST['abort']))
		{
			// feil o_id?
			if (postval("o_id") != $oppdrag['o_id']) \redirect::handle();

			// godkjent?
			if (isset($_POST['confirm']))
			{
				$this->user->player->oppdrag->failed($oppdrag['o_id'], 'Du avbrøt oppdraget &laquo;$name&raquo;. Oppdraget ble derfor mislykket.');
				\ess::$b->page->add_message("Du avbrøt oppdraget. Oppdraget ble derfor mislykket.");
				return \redirect::handle();
			}

			$hidden_inputs = array(
				array("name" => "o_id", "value" => $oppdrag['o_id']),
				array("name" => "abort", "value" => "")
			);

			return View::forgeTwig("helpers/confirm", array(
				"title" => 'Avbryte «'.htmlspecialchars($oppdrag['o_title']).'»',
				"description" => "Hvis du avbryter oppdraget vil oppdraget bli mislykket. Du må da vente 30 minutter før du kan forsøke på dette oppdraget igjen. I tillegg vil du komme i fengsel i 15 minutter.",
				"hidden_inputs" => $hidden_inputs,
				"form_button_text" => "Avbryt oppdrag",
				"cancel_href" => "/oppdrag",
				"cancel_text" => "Ikke avbryt oppdraget",
				"div_size" => "medium",
			));
		}

		// vise en bestemt side? // TODO: rework this
		if (!isset($_GET['force']))
		{
			switch ($trigger->get("name"))
			{
				case "single_poker":
					require PATH_APP."/game/oppdrag/single_poker.php";
					return \ess::$b->page->load();
					break;
			}
		}

		return View::forgeTwig("game/oppdrag/info_active", array(
			"title" => $oppdrag['o_title'],
			"description" => $this->user->player->oppdrag->get_description($oppdrag['o_id']),
			"status" => $this->user->player->oppdrag->status($this->user->player->oppdrag->active['o_id']),
			"hidden_inputs" => array(array("name" => "o_id", "value" => $oppdrag['o_id'])),
			"date_start" => \ess::$b->date->get($oppdrag['uo_active_time'])->format(\date::FORMAT_SEC),
			"date_end" => \ess::$b->date->get($expire)->format(\date::FORMAT_SEC),
			"time_left" => \game::timespan($expire, \game::TIME_ABS),
			"oppdrag_name" => $trigger->get("name")
		));
	}

	public function action_start_oppdrag() {
		$o_id = (int) getval("o_id");

		// kontroller oppdraget
		$this->user->player->oppdrag; // last inn oppdrag om det ikke er lasta inn
		if (!isset($this->user->player->oppdrag->oppdrag[$o_id]) || $this->user->player->oppdrag->oppdrag[$o_id]['uo_locked'] == 1)
		{
			return \redirect::handle("oppdrag");
		}

		$oppdrag = $this->user->player->oppdrag->oppdrag[$o_id];

		// ikke gått lang nok tid?
		if ($oppdrag['uo_last_state'] == 0 && $oppdrag['uo_last_time']+$oppdrag['o_retry_wait'] > time())
		{
			return \redirect::handle("oppdrag");
		}

		// godkjent?
		if (isset($_POST['confirm']))
		{
			// sett oppdraget som aktivt
			if (!$this->user->player->oppdrag->active_set($o_id))
			{
				return \redirect::handle();
			}

			// sett nødvendige verdier
			if (isset($this->user->player->oppdrag->triggers_id[$o_id]))
			{
				$trigger = $this->user->player->oppdrag->triggers_id[$o_id];

				switch ($trigger['trigger']->get("name"))
				{
					case "rank_points":
						$trigger['status']->update("target_points", login::$user->player->data['up_points']+$trigger['trigger']->get("points"));
						$this->user->player->oppdrag->update_status($trigger['o_id'], $trigger['status']);
						break;

					case "single_poker":
						$trigger['status']->update("chips", $trigger['trigger']->get("chips_start"));
						$this->user->player->oppdrag->update_status($trigger['o_id'], $trigger['status']);
						break;
				}
			}

			return \redirect::handle();
		}

		return View::forgeTwig("game/oppdrag/start", array(
			"title" => $oppdrag['o_title'],
			"oppdrag_description" => $this->user->player->oppdrag->get_description($oppdrag['o_id']),
			"hidden_inputs" => array(array("name" => "o_id", "value" => $oppdrag['o_id'])),
			"form_button_text" => "Start oppdrag",
			"cancel_href" => "/oppdrag"
		));
	}

}