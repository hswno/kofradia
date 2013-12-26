<?php

require "../../app/ajax.php";

// krev bruker
ajax::require_user();

// behandle forespørsel
ajax_inbox::load();

class ajax_inbox
{
	public static function load()
	{
		// skal vi behandle en meldingstråd?
		if (isset($_GET['it']))
		{
			return self::load_thread($_GET['it']);
		}
		
		// ukjent handling
		ajax::text("UKJENT", ajax::TYPE_INVALID);
	}
	
	/** Behandle meldingstråd */
	public static function load_thread($it_id)
	{
		$it_id = (int) $it_id;
		
		// mangler ønsket handling?
		if (!isset($_GET['a'])) ajax::text("UKJENT-HANDLING", ajax::TYPE_INVALID);
		$a = $_GET['a'];
		
		// forsøk å hent tråden
		essentials::load_module("inbox_thread");
		if (!($thread = inbox_thread_ajax::get($it_id)))
		{
			// fant ikke tråden
			ajax::text("404", ajax::TYPE_404);
		}
		
		// sjekk for tilgang
		$thread->check_rel();
		
		// ønsket handling
		switch ($a)
		{
			// hente nye svar
			case "new_replies":
				self::thread_check_new_replies($thread);
			break;
			
			// markere/fjerne markering av meldingstråd
			case "mark":
				self::thread_mark($thread);
			break;
			
			default:
				ajax::text("UKJENT-HANDLING: $a", ajax::TYPE_INVALID);
		}
	}
	
	/**
	 * Sjekk for nye svar i forumtråd
	 * @param inbox_thread_ajax $thread
	 */
	public static function thread_check_new_replies(inbox_thread_ajax $thread)
	{
		// mangler vi siste meldings-ID?
		if (!isset($_POST['im_id']))
		{
			ajax::text("MANGLER-SISTE-MELDING", ajax::TYPE_INVALID);
		}
		$im_id = (int) $_POST['im_id'];
		$last_id = $im_id;
		
		// finn ut antal meldinger
		$num_messages = $thread->num_messages();
		
		// hent nye meldinger
		$result = $thread->get_messages(NULL, NULL, "im_id > $im_id");
		
		// har vi nye meldinger?
		$messages = array();
		if (mysql_num_rows($result) > 0)
		{
			// forsøk å sette ned meldingstelleren
			$thread->counter_new_reset();
			
			// gå gjennom meldingene
			$i = 0;
			$messages = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$e = $num_messages - $i;
				if ($i == 0) $last_id = $row['im_id'];
				
				$messages[$row['im_id']] = $thread->reply_format($row, $e, false, true);
				$i++;
			}
		}
		
		// sett opp xml
		$xml = '<list it_id="'.$thread->id.'" last_im_id="'.$last_id.'">';
		
		// har vi noen meldinger?
		if (count($messages) > 0)
		{
			// fiks HTML
			$messages = parse_html_array(array_reverse($messages));
			
			// legg til meldingene
			foreach ($messages as $key => $message)
			{
				$xml .= '<message id="'.$key.'">'.htmlspecialchars($message).'</message>';
			}
		}
		
		$xml .= '</list>';
		
		// send xml
		ajax::xml($xml);
	}
	
	/**
	 * Markere/fjerne markering av meldingstråd
	 * @param inbox_thread_ajax $thread
	 */
	public static function thread_mark(inbox_thread_ajax $thread)
	{
		// kontroller SID
		ajax::validate_sid();
		
		// kan vi ikke markere denne tråden?
		if (!$thread->data_rel)
		{
			ajax::text("NO-RELATION", ajax::TYPE_INVALID);
		}
		
		// skal vi fjerne eller legge til markering?
		if (!isset($_POST['mark']))
		{
			ajax::text("MISSING-MARK", ajax::TYPE_INVALID);
		}
		$mark = $_POST['mark'] != 0;
		
		// forsøk å markere tråden
		$thread->mark($mark);
	}
}