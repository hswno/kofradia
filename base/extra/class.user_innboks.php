<?php

class user_innboks
{
	/**
	 * Brukeren
	 * @var user
	 */
	public $u;
	
	public function __construct(user $u)
	{
		$this->u = $u;
	}
	
	/**
	 * Fiks teller for nye meldinger
	 */
	public function fix_new()
	{
		// oppdater uleste meldinger hos brukeren
		ess::$b->db->query("
			UPDATE users, (
				SELECT up_u_id, SUM(ABS(ir_unread)) c
				FROM users_players LEFT JOIN inbox_rel ON ir_up_id = up_id AND ir_deleted = 0
				WHERE up_u_id = {$this->u->id}
			) r
			SET u_inbox_new = c
			WHERE u_id = up_u_id");
		
		// hent og lagre i objektet vårt
		$result = ess::$b->db->query("
			SELECT u_inbox_new
			FROM users
			WHERE u_id = {$this->u->id}");
		$this->u->data['u_inbox_new'] = mysql_result($result, 0);
	}
	
	/**
	 * Slett meldinger
	 * @return antall meldinger slettet
	 */
	public function delete_specific(array $it_list)
	{
		if (count($it_list) == 0) throw new HSException("Ingen meldinger å slette.");
		$it_list = array_map("intval", $it_list);
		
		// forsøk å slette meldingstråder
		ess::$b->db->query("
			UPDATE inbox_rel JOIN users_players ON up_u_id = {$this->u->id} AND ir_up_id = up_id
			SET ir_deleted = 1
			WHERE ir_it_id IN (".implode(",", $it_list).") AND ir_deleted = 0");
		
		// ble noen slettet
		$deleted = ess::$b->db->affected_rows();
		if ($deleted > 0)
		{
			// oppdater uleste meldinger hos brukeren
			$this->fix_new();
		}
		
		return $deleted;
	}
	
	/**
	 * Slett meldinger eldre enn et gitt tidspunkt
	 * @param slett meldinger til og med dette tidspunktet
	 * @return antall slettet
	 */
	public function delete_older($time)
	{
		if ((int) $time != $time) throw new HSException("Ugyldig tid.");
		$time = (int) $time;
		
		// forsøk og slett meldingstråder
		ess::$b->db->query("
			UPDATE inbox_rel JOIN users_players ON up_u_id = {$this->u->id} AND ir_up_id = up_id
			SET ir_deleted = 1
			WHERE ir_deleted = 0 AND ir_restrict_im_time <= $time AND ir_marked = 0");
		
		// ble noen slettet
		$deleted = ess::$b->db->affected_rows();
		if ($deleted > 0)
		{
			// oppdater uleste meldinger hos brukeren
			$this->fix_new();
		}
		
		return $deleted;
	}
	
	/**
	 * Hent meldinger og data for meldingene
	 */
	public function get_messages(pagei $pagei, $show_deleted = false)
	{
		$result = $pagei->query("
			SELECT it_id, it_title, ir_unread, ir_restrict_im_time, ir_up_id, ir_deleted, ir_marked, COUNT(im_id) num_messages
			FROM inbox_threads
				JOIN inbox_rel ON it_id = ir_it_id
				JOIN users_players ON up_u_id = {$this->u->id} AND ir_up_id = up_id
				JOIN inbox_messages ON im_it_id = it_id AND im_deleted = 0 AND im_time <= ir_restrict_im_time
			WHERE 1" . ($show_deleted ? '' : ' AND ir_deleted = 0') . "
			GROUP BY it_id
			ORDER BY (ir_unread != 0 AND ir_deleted = 0) DESC, ir_marked = 0, ir_restrict_im_time DESC");
		
		// ingen meldinger?
		if ($pagei->total == 0) return array();
		
		// sett opp data for meldingene
		$meldinger = array();
		$prev = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['up_prev'] = false;
			$row['up_prev_other'] = false;
			$row['id_text'] = '';
			$row['receivers'] = array();
			$row['receivers_ok'] = false;
			$row['receivers_crew'] = true;
			$prev[] = $row['it_id'];
			$meldinger[$row['it_id']] = $row;
		}
		
		// hent alle deltakerene i meldingstrådene som skal listes opp
		$result = ess::$b->db->query("
			SELECT ir_it_id, ir_up_id, ir_unread, ir_views, ir_deleted, ir_restrict_im_time, ir_marked, COUNT(im_id) AS num_messages, up_access_level, up_u_id, u_access_level, u_active_up_id
			FROM inbox_rel
				JOIN (
					SELECT ir_it_id ref_it_id, MAX(ir_restrict_im_time) ref_ir_restrict_im_time
					FROM inbox_rel, users_players
					WHERE ir_it_id IN (".implode(",", $prev).") AND up_id = ir_up_id AND up_u_id = {$this->u->id}
					GROUP BY ir_it_id
					ORDER BY up_last_online DESC
				) ref ON ref_it_id = ir_it_id
				LEFT JOIN inbox_messages ON im_it_id = ir_it_id AND im_up_id = ir_up_id AND im_deleted = 0 AND im_time <= ref_ir_restrict_im_time
				LEFT JOIN users_players ON up_id = ir_up_id
				LEFT JOIN users ON u_id = up_u_id
			WHERE ir_it_id IN (".implode(",", $prev).")
			GROUP BY ir_it_id, ir_up_id
			ORDER BY up_name");
		$c = access::has("crewet");
		while ($row = mysql_fetch_assoc($result))
		{
			$meldinger[$row['ir_it_id']]['receivers'][] = $row;
			if ($row['ir_up_id'] != $this->u->player->id && $row['ir_deleted'] == 0 && ($row['up_access_level'] != 0 || ($c && $row['u_access_level'] != 0 && $row['u_active_up_id'] == $row['ir_up_id'])))
			{
				$meldinger[$row['ir_it_id']]['receivers_ok'] = true;
				if ($meldinger[$row['ir_it_id']]['receivers_crew'] && !in_array("crewet", access::types($row['up_access_level'])))
				{
					$meldinger[$row['ir_it_id']]['receivers_crew'] = false;
				}
			}
		}
		
		// hent spillerene som har skrevet siste melding (inkludert meg)
		$im_id = array();
		$result = ess::$b->db->query("
			SELECT im_id, im_it_id, im_up_id, is_self
			FROM (
				SELECT im_id, im_it_id, im_up_id, IF(up1.up_u_id = {$this->u->id}, 1, 0) is_self
				FROM inbox_messages
					JOIN users_players up1 ON up1.up_id = im_up_id
					JOIN users_players up2 ON up2.up_u_id = {$this->u->id}
					JOIN inbox_rel ON im_it_id = ir_it_id AND ir_up_id = up2.up_id AND im_time <= ir_restrict_im_time
				WHERE im_it_id IN (" . implode(",", $prev) . ") AND im_deleted = 0
				ORDER BY im_id DESC
			) AS ref
			GROUP BY im_it_id");
		$others = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$meldinger[$row['im_it_id']]['up_prev'] = array($row['is_self'], $row['im_up_id']);
			$im_id[$row['im_id']] = $row['im_it_id'];
			if ($row['is_self']) $others[] = $row['im_it_id'];
		}
		
		// skal vi hente tidligere avsender? (vi har svart sist)
		if (count($others) > 0)
		{
			// hent spillerene som har skrevet siste melding (ekskludert meg)
			$result = ess::$b->db->query("
				SELECT im_it_id, im_up_id
				FROM (
					SELECT im_it_id, im_up_id
					FROM inbox_messages
						JOIN users_players up1 ON up1.up_id = im_up_id AND up1.up_u_id != {$this->u->id}
						JOIN users_players up2 ON up2.up_u_id = {$this->u->id}
						JOIN inbox_rel ON im_it_id = ir_it_id AND ir_up_id = up2.up_id AND im_time <= ir_restrict_im_time
					WHERE im_it_id IN (" . implode(",", $others) . ") AND im_deleted = 0
					ORDER BY im_id DESC
				) AS ref
				GROUP BY im_it_id");
			$others = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$meldinger[$row['im_it_id']]['up_prev_other'] = $row;
			}
		}
		
		// hent innholdet til de siste meldingene
		if (count($im_id) > 0)
		{
			$result = ess::$b->db->query("
				SELECT id_im_id, id_text FROM inbox_data WHERE id_im_id IN (".implode(",", array_keys($im_id)).")");
			$max = 50;
			while ($row = mysql_fetch_assoc($result))
			{
				$d = strip_tags(game::format_data($row['id_text']));
				$d = preg_replace("/(^ +| +$|\\r)/m", "", $d);
				$d = preg_replace("/(?<![!,.\\n ])\\n/", ". ", $d);
				$d = preg_replace("/\\n/", " ", $d);
				$d = preg_replace("/  +/", " ", $d);
				$d = trim($d);
				if (strlen($d) > $max)
				{
					// TODO: Flytt funksjon til en klasse/funksjon så den kan gjenbrukes av andre sider
					// forsøk å bryt på et mellomrom
					$pos = strpos($d, " ", $max - 10);
					if ($pos !== false && $pos < $max)
						$d = substr($d, 0, $pos) . " ...";
					else
						$d = substr($d, 0, $max - 3) . "...";
				}
				$meldinger[$im_id[$row['id_im_id']]]['id_text'] = $d;
			}
		}
		
		return $meldinger;
	}
}