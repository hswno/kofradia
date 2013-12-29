<?php

class julekalender
{
	public $up;
	public $data;

	public function __construct(player $up) {
		$this->up = $up;
	}

	public function load_data($reload = false) {
		if (!$reload && $this->data) return $this->data;

		$result = \Kofradia\DB::get()->query("
			SELECT j_id, j_day, j_question, j_answer, j_firstprice_points, j_firstprice_cash, j_otherprice_points, j_otherprice_cash, j_status
			FROM julekalender");

		$data = array();
		foreach (range(1, 24) as $day) {
			$data[$day] = false;
		}

		while ($row = $result->fetch()) {
			if (!isset($data[$row['j_day']])) continue; // ignorer ukjent dager

			$data[$row['j_day']] = $row;
		}

		$this->data = $data;
		return $this->data;
	}

	public function load_winners() {
		$result = \Kofradia\DB::get()->query("
			SELECT jb_up_id, jb_day, jb_won
			FROM julekalender_bidrag
			WHERE jb_won != 0");

		$data = array();
		while ($row = $result->fetch()) {
			$data[$row['jb_day']][$row['jb_won']][] = $row['jb_up_id'];
		}

		return $data;
	}

	public function load_my_answers() {
		$result = \Kofradia\DB::get()->query("
			SELECT jb_day, jb_answer, jb_status, jb_won, jb_up_id, up_last_online
			FROM julekalender_bidrag, users_players
			WHERE jb_up_id = up_id AND up_u_id = {$this->up->user->id}
			ORDER BY up_last_online");

		$data = array();
		while ($row = $result->fetch()) {
			$data[$row['jb_day']] = $row;
		}

		return $data;
	}

	public function remove_answer($day) {
		$day = (int) $day;

		return \Kofradia\DB::get()->exec("
			DELETE julekalender_bidrag
			FROM julekalender_bidrag, users_players
			WHERE jb_up_id = up_id AND up_u_id = ".$this->up->user->id." AND jb_day = $day");
	}

	public function set_answer($day, $answer) {
		$day = (int) $day;

		\Kofradia\DB::get()->exec("
			INSERT INTO julekalender_bidrag
			SET jb_up_id = {$this->up->id}, jb_day = $day, jb_answer = ".\Kofradia\DB::quote($answer)."
			ON DUPLICATE KEY UPDATE jb_answer = ".\Kofradia\DB::quote($answer));
	}

	public function get_participants_stats() {
		$days = array();
		$result = \Kofradia\DB::get()->query("
			SELECT jb_day, COUNT(jb_up_id) num_up, SUM(IF(jb_status = 1, 1, 0)) num_correct
			FROM julekalender_bidrag, users_players
			WHERE up_id = jb_up_id AND up_access_level != 0 AND up_access_level < ".ess::$g['access_noplay']."
			GROUP BY jb_day");

		while ($row = $result->fetch()) {
			$days[$row['jb_day']] = $row;
		}

		return $days;
	}

	public function get_answers($day) {
		$day = (int) $day;

		$result = \Kofradia\DB::get()->query("
			SELECT jb_id, jb_up_id, up_access_level, jb_answer, jb_status
			FROM julekalender_bidrag JOIN users_players ON jb_up_id = up_id
			WHERE jb_day = $day");

		$data = array();
		while ($row = $result->fetch()) $data[] = $row;

		return $data;
	}

	/**
	 * @param day
	 * @param up_list (key => ok/ign)
	 */
	public function set_answer_statuses($day, $up_list) {
		$answers = $this->get_answers($day);

		foreach ($answers as $row) {
			if (isset($up_list[$row['jb_up_id']]) && $up_list[$row['jb_up_id']] != "")
				$v = $up_list[$row['jb_up_id']] == "ign" ? 2 : 1;
			else
				$v = 0;

			if ($row['up_access_level'] == 0 || $v == 2 || $row['up_access_level'] >= ess::$g['access_noplay'])
				$new_status = 2;
			else
				$new_status = $v == 1 ? 1 : 0;

			if ($new_status != $row['jb_status'])
				$this->set_answer_status($row['jb_id'], $new_status);
		}
	}

	public function set_answer_status($jb_id, $status) {
		$jb_id = (int) $jb_id;
		$status = (int) $status;

		\Kofradia\DB::get()->exec("
			UPDATE julekalender_bidrag
			SET jb_status = $status
			WHERE jb_id = $jb_id");
	}

	public function close_day($day) {
		$day = (int) $day;

		// verifiser at dagen ikke allerede er avsluttet
		$result = \Kofradia\DB::get()->query("
			SELECT j_id, j_status, j_firstprice_points, j_firstprice_cash, j_otherprice_points, j_otherprice_cash
			FROM julekalender
			WHERE j_day = $day");
		$data = $result->fetch();
		if (!$data || $data['j_status'] != 0) return false;

		// sett som under behandling
		$a = \Kofradia\DB::get()->exec("
			UPDATE julekalender
			SET j_status = 2
			WHERE j_id = {$data['j_id']} AND j_status = 0");
		if ($a == 0) return false;

		\Kofradia\DB::get()->exec("
			UPDATE julekalender_bidrag
			SET jb_won = 0
			WHERE jb_day = $day");

		// vinnere
		$res = array(
			1 => null,
			2 => null);

		// plukk ut en førsteplass
		$result = \Kofradia\DB::get()->query("
			SELECT jb_up_id
			FROM julekalender_bidrag JOIN users_players ON jb_up_id = up_id
			WHERE jb_day = $day AND up_access_level != 0 AND jb_status = 1 AND up_access_level < ".ess::$g['access_noplay']."
			ORDER BY RAND()
			LIMIT 1");
		if ($row = $result->fetch()) {
			$this->give_price($day, $row['jb_up_id'], 1, $data['j_firstprice_points'], $data['j_firstprice_cash']);
			$res[1] = $row['jb_up_id'];
		}

		// plukk ut deltakerpremie
		$where = $res[1] ? " AND jb_up_id != {$res[1]}" : "";
		$result = \Kofradia\DB::get()->query("
			SELECT jb_up_id
			FROM julekalender_bidrag JOIN users_players ON jb_up_id = up_id
			WHERE jb_day = $day AND up_access_level != 0$where AND up_access_level < ".ess::$g['access_noplay']."
			ORDER BY RAND()
			LIMIT 1");
		if ($row = $result->fetch()) {
			$this->give_price($day, $row['jb_up_id'], 2, $data['j_otherprice_points'], $data['j_otherprice_cash']);
			$res[2] = $row['jb_up_id'];
		}

		$this->announce($day, $res);

		// avslutt
		\Kofradia\DB::get()->exec("
			UPDATE julekalender
			SET j_status = 1
			WHERE j_id = {$data['j_id']} AND j_status = 2");

		return true;
	}

	private function give_price($day, $up_id, $place, $points, $cash) {
		$up = player::get($up_id);
		$up->increase_rank($points, false);
		$up->update_money($cash, false);

		\Kofradia\DB::get()->exec("
			UPDATE julekalender_bidrag
			SET jb_won = $place
			WHERE jb_day = $day AND jb_up_id = $up_id");

		$note = $place == 1
			? 'Du ble den heldige vinneren av luken for '.$day.'. desember i julekalenderen. Gratulerer!'
			: 'Du ble den heldige vinneren av deltakerpremien for å ha deltatt på luken for '.$day.'. desember i julekalenderen. Gratulerer!';
		$up->add_log("informasjon", $note);
	}

	private function announce($day, $res) {
		if (!$res[1] && !$res[2]) {
			$html = "Ingen vant eller deltok på luken for ".$day.". desember i julekalenderen.";
			$text = $html;
		}

		else {
			$html = "";
			$text = "";

			if ($res[1]) {
				$up = player::get($res[1]);

				$html .= '<user id="'.$res[1].'" /> ble den heldige vinner av luken for '.$day.'. desember i julekalenderen.';
				$text .= $up->data['up_name'].' ble den heldige vinner av luken for '.$day.'. desember i julekalenderen.';
			}

			if ($res[2]) {
				$up = player::get($res[2]);

				if (!$res[1]) {
					$html .= 'Ingen svarte riktig for luken til '.$day.'. desember i julekalenderen.';
					$text .= 'Ingen svarte riktig for luken til '.$day.'. desember i julekalenderen.';
				}

				$html .= ' <user id="'.$res[2].'" /> vant deltakerpremien.';
				$text .= ' '.$up->data['up_name'].' vant deltakerpremien.';
			}
		}

		livefeed::add_row($html);
		putlog("INFO", "%bJULEKALENDER%b: $text");
	}
}