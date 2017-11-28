<?php

class page_julekalender extends pages_player
{
	/**
	 * @var julekalender
	 */
	private $obj;

	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		access::no_guest();

		parent::__construct($up);
		$this->obj = new julekalender($up);

		ess::$b->page->add_js_file("&rpath;/resources/julekalender.js");
		ess::$b->page->add_css_file("&rpath;/resources/julekalender.css");

		// admin?
		if (isset($_GET['jul'])) {
			$this->admin();
		}

		// besvare?
		if (isset($_POST['day'])) {
			$this->respond();
		}

		$this->show();
		
		//ess::$b->page->load();
	}

	private function get_today($obj = false) {
		$d = ess::$b->date->get();
		//$d->modify("-1 day");
		return $obj ? $d : $d->format("j");
	}

	private function check_access_julekalender() {
		return access::has("senior_mod");
	}

	private function admin() {
		if (!$this->check_access_julekalender()) return;

		echo '
<section>
	<h1>Julekalender - admin</h1>
	<p><a href="&rpath;/">Tilbake</a></p>';

		if (!isset($_GET['day']) || !$this->admin_day()) {
			$this->admin_alldays();
		}

		echo '
</section>';

		ess::$b->page->load();
	}

	private function admin_day() {
		$day = (int) getval("day");
		if ($day < 1 || $day > 24) return false;

		$today = $this->get_today();;
		$data = $this->obj->load_data();
		
		// kan ikke behandles?
		if ($day >= $today || !isset($data[$day]) || $data[$day]['j_status'] == 1) redirect::handle("?jul");

		if (isset($_POST['save']) || isset($_POST['end'])) {
			$this->obj->set_answer_statuses($day, (array) postval("up"));

			if (isset($_POST['end'])) {
				$this->obj->close_day($day);
				redirect::handle("?jul");
			}

			ess::$b->page->add_message("Alternativene ble lagret.");
			redirect::handle("?jul&day=$day");
		}
		
		echo '
	<p>Viser dag: '.$day.'. desember</p>
	<p><a href="&rpath;/?jul">Tilbake til oversikten</a></p>
	<p>Spørsmål: '.$data[$day]['j_question'].'</p>
	<p>Riktig svar: '.$data[$day]['j_answer'].'</p>
	<form action="?jul&amp;day='.$day.'" method="post">
		<p><i>Velg de som har gitt riktig besvarelse:</i></p>
		<table class="table">
			<thead>
				<tr>
					<th>Feil</th>
					<th>Korrekt</th>
					<th>Ignorer</th>
					<th>Spiller</th>
					<th>Svar</th>
				</tr>
			</thead>
			<tbody>';

		$answers = $this->obj->get_answers($day);
		$i = 0;
		foreach ($answers as $row) {
			$ign = $row['up_access_level'] == 0 || $row['up_access_level'] >= ess::$g['access_noplay'];
			$checked_ok = $row['jb_status'] == 1 && !$ign;
			$checked_ign = $row['jb_status'] == 2 || $ign;

			$class = $row['up_access_level'] != 0 ? ' class="box_handle"' : '';

			echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td'.$class.'>'.($row['up_access_level'] != 0 ? '<input type="radio" name="up['.$row['jb_up_id'].']" value=""'.(!$checked_ok && !$checked_ign ? ' checked="checked"' : '').' />' : '&nbsp;').'</td>
					<td'.$class.'>'.($row['up_access_level'] != 0 ? '<input type="radio" name="up['.$row['jb_up_id'].']" value="ok"'.($checked_ok ? ' checked="checked"' : '').' />' : '&nbsp;').'</td>
					<td'.$class.'>'.($row['up_access_level'] != 0 ? '<input type="radio" name="up['.$row['jb_up_id'].']" value="ign"'.($checked_ign ? ' checked="checked"' : '').' />' : '&nbsp;').'</td>
					<td><user id="'.$row['jb_up_id'].'" /></td>
					<td>'.nl2br(htmlspecialchars($row['jb_answer'])).'</td>
				</tr>';
		}

		echo '
			</tbody>
		</table>
		<p>'.show_sbutton("Lagre alternativer", 'name="save"').' '.show_sbutton("Lagre og avslutt luken", 'name="end"').'</p>
	</form>';

		ess::$b->page->load();
	}

	private function admin_alldays() {
		echo '
	<table class="table">
		<thead>
			<tr>
				<th>Dag</th>
				<th>Status</th>
				<th>Antall deltakere</th>
			</tr>
		</thead>
		<tbody>';

		$today = $this->get_today();
		
		$data = $this->obj->load_data();
		$stats = $this->obj->get_participants_stats();

		$i = 0;
		foreach ($data as $day => $row) {
			if (!$row) {
				echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td class="r">'.$day.'</td>
				<td colspan="2">Ingen luke</td>
			</tr>';

				continue;
			}

			$status = $day > $today
				? 'Uåpnet'
				: ($day == $today
					? 'Dagens luke'
					: ($row['j_status'] == 1 ? 'Avsluttet' : '<a href="?jul&amp;day='.$day.'">Må behandles</a>'));

			$deltakere = isset($stats[$day]) ? $stats[$day]['num_up'] : '&nbsp;';
			if ($row['j_status'] == 1 && isset($stats[$day])) $deltakere .= ' ('.$stats[$day]['num_correct'].' riktig)';

			echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				<td class="r">'.$day.'</td>
				<td>'.$status.'</td>
				<td>'.$deltakere.'</td>
			</tr>';
		}

		echo '
		</tbody>
	</table>';
	}

	private function respond() {
		$day = postval("day");
		$answer = postval("answer");

		$d = $this->get_today(true);
		$today = $d->format("j");
		$date_ok = $d->format("Y-m") == "2017-12";

		$data = $this->obj->load_data();
		$my_answers = $this->obj->load_my_answers();

		if ($day != $today) {
			ess::$b->page->add_message("Du forsøkte å legge til svar på en annen dag.", "error");
			return;
		}

		// ingen luke?
		if (!$date_ok || !isset($data[$today])) {
			ess::$b->page->add_message("Det er ingen luke for i dag.", "error");
			return;
		}

		if ($answer == "") {
			// fjerne svaret?
			if (isset($my_answers[$today])) {
				$this->obj->remove_answer($today);

				ess::$b->page->add_message("Ditt svar ble fjernet.");
				redirect::handle();
			}

			ess::$b->page->add_message("Du må fylle inn et svar.", "error");
			return;
		}

		if (isset($my_answers[$today]) && $answer == $my_answers[$today]) {
			redirect::handle();
		}

		$this->obj->set_answer($day, $answer);
		ess::$b->page->add_message("Du har lagt til svar for dagens luke.");

		redirect::handle();
	}

	private function show()
	{
		$data = $this->obj->load_data();
		$answers = $this->obj->load_winners();
		$my_answers = $this->obj->load_my_answers();

		$n = 0;
		$today = $this->get_today();

		$admin_link = $this->check_access_julekalender() ? ' - <a href="?jul">admin</a>' : '';

		echo '
<article id="julekalender">
	<h1 class="jul_h">Julekalender'.$admin_link.'</h1>
	<section>
		<div class="jul_rad"><!--';

		foreach ($data as $day) {
			if ($n++ % 6 == 0 && $n != 1) echo '
		--></div>
		<div class="jul_rad"><!--';

			$classes = '';
			if (!$day) $classes .= " no_day";
			else {
				if ($day['j_day'] == $today) {
					$classes .= " today";
					if (isset($_POST['day'])) $classes .= " hover";
				}
				else if ($day['j_day'] > $today) $classes .= " ahead";
				else $classes .= " prev";
			}

			echo '
			--><div class="jul_cell_wrap">
				<div class="jul_cell'.$classes.'">
					<h1>'.$n.'</h1>';

			$my_answer = isset($my_answers[$n]) ? $my_answers[$n] : null;
			$my_answer_value = $my_answer ? $my_answer['jb_answer'] : "";

			// ingen luke
			if (!$day) {
				echo '
					<div class="jul_data">
						<p>Ingen spørsmål denne dagen.</p>
					</div>';
			}
			
			// fremtidig luke?
			elseif ($day['j_day'] > $today) {
				echo '
					<div class="jul_data">
						<p>Kom tilbake '.$n.'. desember for å se denne luken.</p>
					</div>';
			}

			// gammel luke?
			elseif ($day['j_day'] < $today) {
				// ikke avsluttet?
				if ($day['j_status'] == 0) {
					echo '
						<div class="jul_data">
							<p class="question">'.$day['j_question'].'</p>
							<p>Vinner er ikke annonsert enda.</p>'.($my_answer_value ? '
							<p>Ditt svar: '.htmlspecialchars($my_answer_value).'</p>' : '').'
						</div>';
				}

				// avsluttet
				else {
					$winners = array();
					$user = function($up_id) {
						return '<user id="'.$up_id.'" />';
					};
					
					if (isset($answers[$day['j_day']][1]))
						$winners[] = 'Førsteplass: '.sentences_list(array_map($user, $answers[$day['j_day']][1]));

					if (isset($answers[$day['j_day']][2]))
						$winners[] = 'Deltakerpremie: '.sentences_list(array_map($user, $answers[$day['j_day']][2]));

					if (!$winners) $winners[] = 'Ingen deltok i luken.</p>';

					// riktig/galt svar
					$answer_info = '';
					if ($my_answer_value) {
						switch ($my_answer['jb_status']) {
							case 0: $answer_info = 'Feil'; break;
							case 1: $answer_info = 'Korrekt'; break;
							default: $answer_info = 'Ikke deltatt'; break;
						}
					}

					echo '
						<div class="jul_data">
							<p class="question">'.$day['j_question'].'</p>
							<p>Riktig svar: '.$day['j_answer'].($my_answer_value ? '<br />
								Ditt svar: '.htmlspecialchars($my_answer_value).' ('.$answer_info.')' : '').'</p>
							<p>'.implode('<br />
								', $winners).'</p>
						</div>';
				}
			}

			// dagens luke?
			else {
				$up_alert = $my_answer && $my_answer['jb_up_id'] != $this->up->id ? '
							<p><i style="color: #FF0000"><b>Obs!</b> Ditt svar gjelder ikke din nåværende spiller. Du må trykke &quot;svar&quot; for å delta med din nye spiller.</i></p>' : '';

				echo '
					<div class="jul_data">
						<p class="question">'.$day['j_question'].'</p>
						<form action="&rpath;/" method="post">
							<input type="hidden" name="day" value="'.$day['j_day'].'" />'.$up_alert.'
							<p><input type="text" class="styled w180" name="answer" value="'.htmlspecialchars(postval("answer", $my_answer_value)).'" /> '.show_sbutton("Svar").'</p>
							<p><i>Du kan oppdatere svaret ditt frem til midnatt.</i></p>
						</form>
					</div>';
			}

			echo '
				</div>
			</div><!--';
		}

		echo '
		--></div>
		<div class="jul_notes">
			<p>Det velges en tilfeldig vinner hver dag blant alle korrekte svar. Premie: 10 mill kr og 1 500 poeng.</p>
			<p>Det velges også ut en tilfeldig deltaker hver dag av alle som deltar: Premie 5 mill kr og 1 000 poeng.</p>
			<p>Dobbel premie på julaften.</p>
		</div>
	</section>
</article>';
	}
}
