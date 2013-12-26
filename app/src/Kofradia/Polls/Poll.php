<?php namespace Kofradia\Polls;

class Poll
{
	/**
	 * Get polls on the active page
	 *
	 * @param \pagei
	 * @param \user
	 * @return array
	 */
	public static function getPolls(\pagei $pagei, \user $user = null)
	{
		$time = time();
		$result = $pagei->query("
			SELECT p_id, p_active, p_ft_id, p_title, p_text, p_time_start, p_time_end".($user ? ", pv_po_id, pv_time" : "")."
			FROM polls".($user ? "
				LEFT JOIN polls_votes ON pv_up_id = ".$user->player->id." AND pv_p_id = p_id" : "")."
			WHERE p_active != 0 AND p_time_start < $time
			GROUP BY p_id
			ORDER BY p_time_end != 0, p_time_end DESC, p_id DESC");

		if (mysql_num_rows($result) == 0)
		{
			return array();
		}

		$polls = array();

		// les data
		while ($row = mysql_fetch_assoc($result))
		{
			$polls[$row['p_id']] = static::createFromData($row, $user);
		}

		// hent alternativene
		$result = \ess::$b->db->query("
			SELECT po_id, po_p_id, po_text, po_votes
			FROM polls_options
			WHERE po_p_id IN (".implode(",", array_keys($polls)).")");
		while ($row = mysql_fetch_assoc($result))
		{
			new PollOption($row, $polls[$row['po_p_id']]);
		}

		return $polls;
	}
	
	public static function createFromData(array $data, \user $user = null)
	{
		$poll = new static();
		$poll->id = $data['p_id'];
		$poll->data = $data;
		$poll->user = $user;
		return $poll;
	}

	/**
	 * Load specific poll
	 *
	 * @param int
	 * @param \user
	 * @return \Kofradia\Polls\Poll
	 */
	public static function load($poll_id, \user $user = null)
	{
		$poll_id = \ess::$b->db->quote($poll_id);
		$result = \ess::$b->db->query("
			SELECT p_id, p_active, p_ft_id, p_title, p_text, p_time_start, p_time_end".($user ? ", pv_po_id, pv_time" : "")."
			FROM polls".($user ? "
				LEFT JOIN polls_votes ON pv_up_id = ".$user->player->id." AND pv_p_id = p_id" : "")."
			WHERE p_id = $poll_id
			GROUP BY p_id");

		if ($row = mysql_fetch_assoc($result))
		{
			return static::createFromData($row, $user);
		}
	}

	public $id;
	public $data;
	public $options = array();
	protected $options_loaded;
	public $votes = 0;

	/**
	 * User it is binded to, if any
	 * @var \user
	 */
	protected $user;

	public function __construct() {}

	public function addOption(PollOption $option)
	{
		$this->options[] = $option;
		$this->votes += $option->getNumVotes();

		$this->options_loaded = true;
	}

	public function loadOptions()
	{
		$this->options = array();

		$result = \ess::$b->db->query("
			SELECT po_id, po_p_id, po_text, po_votes
			FROM polls_options
			WHERE po_p_id = $this->id");
		while ($row = mysql_fetch_assoc($result))
		{
			new PollOption($row, $this);
		}

		$this->options_loaded = true;
	}

	/**
	 * Get a user's vote
	 *
	 * @param \user $user
	 * @return \Kofradia\Polls\PollOption
	 */
	public function getVote(\user $user = null)
	{
		if (is_null($user) && is_null($this->user))
		{
			throw new \HSException("Unknown user.");
		}

		if (is_null($user) || $user == $this->user)
		{
			$option_id = $this->data['pv_po_id'];
		}
		else
		{
			$result = \ess::$b->db->query("
				SELECT pv_po_id
				FROM polls_votes
				WHERE pv_p_id = $this->id AND pv_up_id = ".$user->player->id);
			if (mysql_num_rows($result) == 0)
			{
				return null;
			}

			$option_id = mysql_result($result, 0);
		}

		return $this->findOption($option_id);
	}

	/**
	 * Return a option by its ID
	 *
	 * @param int id
	 * @return \Kofradia\Polls\PollOption
	 */
	public function findOption($option_id)
	{
		// load options
		if (!$this->options_loaded)
		{
			$this->loadOptions();
		}

		foreach ($this->options as $option)
		{
			if ($option->id == $option_id)
			{
				return $option;
			}
		}
	}

	/**
	 * Is it active?
	 *
	 * @return bool
	 */
	public function isActive()
	{
		return $this->data['p_active'] != 0;
	}

	/**
	 * Is it available?
	 * Must be active and within the time
	 *
	 * @return bool
	 */
	public function isAvailable()
	{
		$time = time();
		$time_ok = $this->data['p_time_start'] < $time && (!$this->data['p_time_end'] || $time <= $this->data['p_time_end']);

		return $time_ok && $this->isActive();
	}
}