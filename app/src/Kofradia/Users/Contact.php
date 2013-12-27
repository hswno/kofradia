<?php namespace Kofradia\Users;

class Contact {
	/**
	 * Contact type: friend
	 */
	const TYPE_FRIEND = 1;

	/**
	 * Contact type: block
	 */
	const TYPE_BLOCK = 2;

	/**
	 * Max length of info text
	 */
	const MAX_INFO_LENGTH = 200;

	/**
	 * Get list of contacts for a user
	 *
	 * @param \user
	 * @param null|string  column to order by
	 * @return array of contact-objects
	 */
	public static function getContacts(\user $owner_user, $order_col = null)
	{
		$contacts = array();

		$order = !is_null($order_col) ? $order_col : null;
		$result = static::getData("uc_u_id = ".$owner_user->id, $order);

		while ($row = mysql_fetch_assoc($result))
		{
			$contacts[] = static::getInstance($row, $owner_user);
		}

		return $contacts;
	}

	/**
	 * Get a specific contact-object by contact entry ID
	 *
	 * @param int  the ID
	 * @return \Kofradia\Users\Contact
	 */
	public static function getContactById($id)
	{
		$id = \ess::$b->db->quote($id);
		$result = static::getData("uc_id = $id");

		if ($row = mysql_fetch_assoc($result))
		{
			return static::getInstance($row);
		}
	}

	/**
	 * Get a specific contact-object by owner user, player and type
	 *
	 * @param \user     Owner
	 * @param \player   Refers
	 * @param null|bool True for blocks, false for normal contacts, null for both
	 * @return mixed    Returns \Kofradia\Users\Contact if third arg is bool, else array of it. Null/empty if none found.
	 */
	public static function getContactByPlayer(\user $owner_user, \player $target_player, $is_block = null)
	{
		$block = is_null($is_block) ? '' : ' AND uc_type = '.($is_block ? static::TYPE_BLOCK : static::TYPE_FRIEND);
		$result = static::getData("uc_u_id = $owner_user->id AND uc_contact_up_id = $target_player->id$block");

		if (!is_null($is_block))
		{
			$row = mysql_fetch_assoc($result);
			return $row ? static::getInstance($row) : null;
		}

		$rows = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$rows[] = static::getInstance($row);
		}

		return $rows;
	}

	/**
	 * Fetch data from database specifying where condition and optinally the order
	 *
	 * @param string WHERE-clause (without WHERE)
	 * @param string ORDER-clause (without ORDER)
	 * @return result object
	 */
	protected function getData($where, $order = null)
	{
		$order = !empty($order) ? ' ORDER BY '.$order : '';
		return \ess::$b->db->query("
			SELECT uc_id, uc_u_id, uc_contact_up_id, uc_time, uc_info, uc_type, up_name, up_access_level, up_last_online
			FROM users_contacts
				LEFT JOIN users_players ON up_id = uc_contact_up_id
			WHERE $where$order");
	}


	/**
	 * Create a new contact-object and add to database
	 *
	 * @param \user        The user it belongs
	 * @param \player      The player it refers to
	 * @param null|string  Comment about the contact
	 * @param bool         Is block?
	 * @return \Kofradia\Users\Contact  The new object
	 */
	public static function create(\user $user, \player $player, $comment = null, $is_block = false)
	{
		\ess::$b->db->query("
			INSERT IGNORE INTO users_contacts
			SET uc_u_id = {$user->id}, uc_contact_up_id = {$player->id}, uc_time = ".time().",
				uc_type = ".($is_block ? static::TYPE_BLOCK : static::TYPE_FRIEND).", uc_info = ".\ess::$b->db->quote($comment));

		if (\ess::$b->db->affected_rows())
		{
			$contact = static::getContactByID(\ess::$b->db->insert_id());
			$contact->getOwnerUser()->updateContactsTime();
			return $contact;
		}
	}

	/**
	 * Validate length of info text
	 */
	public static function validateInfoLength($text)
	{
		$text = strip_tags(\game::bb_to_html($text));
		return mb_strlen($text) <= static::MAX_INFO_LENGTH;
	}

	/**
	 * Genereate an instance from data and return it
	 *
	 * @param array Data from database
	 * @param \user The user it belongs
	 * @return \Kofradia\Users\Contact
	 */
	public static function getInstance($data, \user $owner_user = null)
	{
		$c = new static();
		$c->data = $data;
		$c->owner_user = $owner_user;
		return $c;
	}

	/**
	 * @var \user
	 */
	public $owner_user;
	
	/**
	 * @var \player
	 */
	public $target_player;
	
	/**
	 * The data
	 */
	public $data;

	/**
	 * Get type ID for this entry
	 *
	 * @return int See TYPE-constants.
	 */
	public function getType()
	{
		return $this->data['uc_type'];
	}

	/**
	 * Get object for the user this contact entry belongs
	 *
	 * @return \user
	 */
	public function getOwnerUser()
	{
		if (is_null($this->owner_user))
		{
			$this->owner_user = \user::get($this->data['uc_u_id']);
		}

		return $this->owner_user;
	}

	/**
	 * Get id for the user this contact entry belongs
	 *
	 * @return int
	 */
	public function getOwnerUserID()
	{
		return $this->data['uc_u_id'];
	}

	/**
	 * Get player object for the player this contact entry refers
	 *
	 * @return \player
	 */
	public function getTargetPlayer()
	{
		if (is_null($this->target_player))
		{
			$this->target_player = \player::get($this->data['uc_contact_up_id']);
		}

		return $this->target_player;
	}

	/**
	 * Get id for the player this contact entry refers
	 *
	 * @return int
	 */
	public function getTargetPlayerID()
	{
		return $this->data['uc_contact_up_id'];
	}

	/**
	 * Is this a block (not "normal" contact)?
	 *
	 * @return bool
	 */
	public function isBlock()
	{
		return $this->data['uc_type'] == static::TYPE_BLOCK;
	}

	/**
	 * Set new info text and save
	 *
	 * @param string The new text
	 * @return bool  True if success
	 */
	public function updateInfo($info)
	{
		\ess::$b->db->query("
			UPDATE users_contacts
			SET uc_info = ".\ess::$b->db->quote($info)."
			WHERE uc_id = {$this->data['uc_id']}");

		if (\ess::$b->db->affected_rows() > 0)
		{
			$this->data['uc_info'] = $info;
			$this->getOwnerUser()->updateContactsTime();
			return true;
		}

		return false;
	}

	/**
	 * Delete contact entry
	 *
	 * @return bool True if success
	 */
	public function delete()
	{
		\ess::$b->db->query("
			DELETE FROM users_contacts
			WHERE uc_id = {$this->data['uc_id']}");

		if (\ess::$b->db->affected_rows() > 0)
		{
			$this->getOwnerUser()->updateContactsTime();
			return true;
		}

		return false;
	}
}