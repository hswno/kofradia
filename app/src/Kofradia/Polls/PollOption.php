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
		\ess::$b->db->query("
			INSERT IGNORE INTO polls_votes
			SET pv_p_id = {$this->poll->id}, pv_po_id = {$this->id}, pv_up_id = {$user->player->id}, pv_time = ".time());

		$ok = \ess::$b->db->affected_rows() > 0;
		if ($ok)
		{
			\ess::$b->db->query("UPDATE polls_options SET po_votes = po_votes + 1 WHERE po_id = $this->id");
			\ess::$b->db->query("UPDATE polls SET p_votes = p_votes + 1 WHERE p_id = {$this->poll->id}");

			// delete vote cache
			\cache::delete("polls_options_list");
		}

		return $ok;
	}
}