<?php namespace Kofradia\Polls;

class PollOption {
	public $id;
	public $poll;
	public $data;

	public function __construct(array $data, Poll $poll)
	{
		$this->id = $data['po_id'];
		$this->data = $data;
		$this->poll = $poll;

		$this->poll->addOption($this);
	}

	public function getNumVotes()
	{
		return $this->data['po_votes'];
	}

	/**
	 * Vote on this
	 *
	 * @param \user User voting
	 * @return bool
	 */
	public function vote(\user $user)
	{
		$a = \Kofradia\DB::get()->exec("
			INSERT IGNORE INTO polls_votes
			SET pv_p_id = {$this->poll->id}, pv_po_id = {$this->id}, pv_up_id = {$user->player->id}, pv_time = ".time());

		if ($a > 0)
		{
			\Kofradia\DB::get()->exec("UPDATE polls_options SET po_votes = po_votes + 1 WHERE po_id = $this->id");
			\Kofradia\DB::get()->exec("UPDATE polls SET p_votes = p_votes + 1 WHERE p_id = {$this->poll->id}");

			// delete vote cache
			\cache::delete("polls_options_list");
		}

		return $ok;
	}

	/**
	 * Get percent of total votes this option represents
	 *
	 * With one decimal
	 *
	 * @return float
	 */
	public function getPercent()
	{
		if ($this->poll->votes == 0)
		{
			return 0;
		}

		return round($this->data['po_votes'] / $this->poll->votes * 100, 1);
	}
}