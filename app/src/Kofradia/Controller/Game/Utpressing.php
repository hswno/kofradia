<?php namespace Kofradia\Controller\Game;

use \Kofradia\View;

class Utpressing extends \Kofradia\Controller\Game {
	/**
	 * Count between anti-bot checks
	 *
	 * @var int
	 */
	const ANTIBOT_SPAN = 10;

	/**
	 * Skjema
	 * @var \Kofradia\Form
	 */
	protected $form;

	/**
	 * Anti-bot
	 * @var antibot
	 */
	protected $antibot;

	/**
	 * Utpressing-objekt
	 *
	 * @var \Kofradia\Game\Utpressing
	 */
	protected $ut;
	
	public function action_index()
	{
		$this->needUser();
		$this->ut = new \Kofradia\Game\Utpressing($this->user->player);

		\ess::$b->page->add_title("Utpressing");
		\kf_menu::$data['utpressing'] = true;

		// kontroller fengsel, bomberom og energi
		$this->user->player->fengsel_require_no();
		$this->user->player->bomberom_require_no();
		$this->user->player->energy_require(\Kofradia\Game\Utpressing::ENERGY*1.3); // legg til 30 % for krav
		
		// kontroller anti-bot
		$this->antibot = \antibot::get("utpressing", static::ANTIBOT_SPAN);
		$this->antibot->check_required();
		
		// skjema
		$this->form = \Kofradia\Form::getByDomain("utpressing", $this->user);

		// sett opp hvilke ranker som kan angripes
		$this->rank_min = max(1, $this->user->player->rank['number'] - 1);
		$this->rank_max = min($this->rank_min + 3, count(\game::$ranks['items']));
		if ($this->rank_max - $this->rank_min < 3) $this->rank_min = max(1, $this->rank_max - 3); // sørg for at man har 4 alternativer uavhengig av rank

		// utføre utpressing?
		if (isset($_POST['utpressing']))
		{
			$ret = $this->utpress();
			if (!$ret)
			{
				return \redirect::handle();
			}
			return $ret;
		}

		return $this->showForm();
	}

	/**
	 * Show the form
	 */
	protected function showForm()
	{	
		return View::forgeTwig('game/utpressing/form', array(
			"match" => \ess::session_get("utpressing_opt_key") ?: null,
			"wait" => $this->ut->getWait(),
			"options" => $this->ut->getOptions(),
			"form" => $this->form));
	}

	/**
	 * List of last utpressinger
	 */
	public function action_log()
	{
		$this->needUser();
		$this->ut = new \Kofradia\Game\Utpressing($this->user->player);

		\ess::$b->page->add_title("Utpressing");
		\ess::$b->page->add_title("Siste utpressinger");
		\kf_menu::$data['utpressing'] = true;

		$pagei = new \pagei(\pagei::PER_PAGE, 20, \pagei::ACTIVE_GET, "side");
		$expire = time()-43200;
		$rows = $this->ut->getLast($pagei, $expire);
		
		return View::forge('game/utpressing/log', array(
			"list" => $rows,
			"pagei" => $pagei));
	}

	/**
	 * Kontroller inndata og utfør utpressing
	 */
	public function utpress()
	{
		// wait time?
		if (($wait = $this->ut->getWait()) > 0)
		{
			\ess::$b->page->add_message("Du må vente ".\game::counter($wait, true)." før du kan utføre en ny utpressing.", "error");
			return;
		}
		
		// validate form
		$form_info = '';
		if ($this->ut->up->data['up_utpressing_last'])
		{
			$form_info = sprintf("Siste=%s;",
				\game::timespan($this->ut->up->data['up_utpressing_last'], \game::TIME_ABS | \game::TIME_SHORT | \game::TIME_NOBOLD));
		}
		else
		{
			$form_info = "First;";
		}
		if ($wait)
		{
			$form_info .= sprintf("%%c11Ventetid=%s%%c",
				\game::timespan($wait, \game::TIME_SHORT | \game::TIME_NOBOLD));
		}
		else
		{
			$form_info .= "%c9No-wait%c";
		}
		if (!$this->form->validateHashOrAlert(postval('hash'), $form_info))
		{
			return;
		}
		
		// mangler alternativ?
		$option = $this->ut->getOption(postval("opt"));
		if (!$option)
		{
			\ess::$b->page->add_message("Du må velge et alternativ.", "error");
			return;
		}

		// lagre valg for neste gang
		\ess::session_put("utpressing_opt_key", postval("opt"));
		
		// forsøk utpressing
		$result = $this->ut->utpress($option);
		if ($msg = $result->getMessage())
		{
			\ess::$b->page->add_message($msg);
		}

		// oppdater anti-bot
		$this->antibot->increase_counter();
	}
}