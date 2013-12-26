<?php

class mailer
{
	/** Hvor lang tid det skal gå før vi prøver å sende en tidligere e-post på nytt */
	public static $timeout = 600;
	
	/** Send ut e-poster */
	public static function parse_queue($limit)
	{
		global $_base;
		
		// begrensning: min 1 og maks 100
		$limit = min(100, max(1, intval($limit)));
		
		// generer id
		$id = uniqid("", true);
		
		// sett tags på e-poster vi ønsker å hente
		$_base->db->query("UPDATE mailer SET m_active_end = ".(time()+self::$timeout).", m_active_id = ".$_base->db->quote($id)." WHERE (m_active_end IS NULL OR m_active_end < ".time().") LIMIT $limit");
		
		// ingen endret?
		if ($_base->db->affected_rows() == 0)
		{
			return 0;
		}
		
		// send e-postene som ble tagget
		return self::send_tag($id);
	}
	
	/**
	 * Send e-poster som er tagget med et bestemt tag
	 * @param string unik id som er lagt til i tabellen for e-postene
	 */
	protected static function send_tag($tag)
	{
		global $_base;
		
		// hent alle e-postene vi tagget og forsøk å send de
		$result = $_base->db->query("SELECT m_id, m_receiver, m_subject, m_headers, m_body, m_params FROM mailer WHERE m_active_id = ".$_base->db->quote($tag));
		
		$sent = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			// send e-posten
			if (mb_send_mail($row['m_receiver'], $row['m_subject'], $row['m_body'], $row['m_headers'], $row['m_params']))
			{
				// fjern fra databasen
				$_base->db->query("DELETE FROM mailer WHERE m_id = {$row['m_id']}");
				
				$sent++;
			}
		}
		
		return $sent;
	}
	
	/**
	 * Legg til e-post i køen
	 * @param object email $email
	 * @param array receivers
	 * @param string subject
	 * @param bool sende e-posten med en gang
	 */
	public static function add_emails(email $email, $receivers, $subject, $send_now = false)
	{
		global $_base;
		
		if (!is_array($receivers))
		{
			$receivers = array($receivers);
		}
		
		if (!isset($email->data) || !$email->data)
		{
			throw new HSException("Email must be formatted before input.");
		}
		
		// sett opp tag
		$id = uniqid("", true);
		
		$add = array();
		foreach ($receivers as $item)
		{
			$more = $send_now ? ", ".(time()+self::$timeout).", ".$_base->db->quote($id) : "";
			$add[] = "(".$_base->db->quote($item).",".$_base->db->quote($subject).",".$_base->db->quote($email->data[0]).",".$_base->db->quote($email->data[1]).",".$_base->db->quote($email->params)."$more)";
		}
		
		// noen vi skal legge til?
		if (count($add) > 0)
		{
			$more = $send_now ? ", m_active_end, m_active_id" : "";
			$_base->db->query("INSERT INTO mailer (m_receiver, m_subject, m_headers, m_body, m_params$more) VALUES ".implode(", ", $add));
		}
		
		// skal e-postene sendes med en gang?
		if ($send_now)
		{
			return array($add, self::send_tag($id));
		}
		
		return count($add);
	}
}