<?php

require "config.php";
new page_crewloggs();

class page_crewloggs
{
	protected $actions;
	protected $actions_all;
	
	protected $filters_active;
	protected $filter_actions;
	protected $filter_by_up;
	protected $filter_a_up;
	protected $filter_time_before;
	protected $filter_time_after;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		ess::$b->page->add_title("Logg");
		
		// vise en spesifik hendelse
		if (isset($_GET['lc_id']))
		{
			$this->handle_specific();
		}
		
		$this->load_actions();
		$this->check_filters();
		
		$this->show();
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis en bestemt hendelse
	 */
	protected function handle_specific()
	{
		$lc_id = (int) $_GET['lc_id'];
		$result = ess::$b->db->query("SELECT lc_id, lc_up_id, lc_time, lc_lca_id, lc_a_up_id, lc_log FROM log_crew WHERE lc_id = $lc_id");
		$lc = mysql_fetch_assoc($result);
		
		if (!$lc)
		{
			ess::$b->page->add_message("Fant ikke oppføringen med ID #$lc_id.");
			redirect::handle();
		}
		
		$lc_action = crewlog::$actions[crewlog::$actions_id[$lc['lc_lca_id']]];
		
		// hent data
		$result = ess::$b->db->query("
			SELECT lcd_lc_id, lcd_lca_id, lcd_lce_id, lcd_data_int, lcd_data_text, lce_type
			FROM log_crew_data, log_crew_extra
			WHERE lcd_lc_id = $lc_id AND lcd_lca_id = lce_lca_id AND lcd_lce_id = lce_id");
		$data = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$data_name = $lc_action[5][$row['lcd_lce_id']][0];
			$data[$data_name] = $row['lce_type'] == "int" ? $row['lcd_data_int'] : $row['lcd_data_text'];
		}
		
		// redigere?
		if (isset($_GET['edit']))
		{
			$this->handle_specific_edit($lc, $lc_action, $data);
		}
		
		ess::$b->page->add_title("Oppføring: $lc_id (".htmlspecialchars($lc_action[4]).")");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Viser loggoppføring<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="crewlogg">&laquo; Tilbake</a></p>
	<div class="bg1">
		<dl class="dd_right">
			<dt>Logg ID</dt>
			<dd>'.$lc['lc_id'].'</dd>
			<dt>Logg gruppe</dt>
			<dd>'.htmlspecialchars(crewlog::$actions_groups[$lc_action[1]]).'</dd>
			<dt>Handling</dt>
			<dd>'.htmlspecialchars($lc_action[4]).'</dd>
			<dt>Utført av</dt>
			<dd><user id="'.$lc['lc_up_id'].'" /></dd>'.($lc['lc_a_up_id'] ? '
			<dt>Påvirket spiller</dt>
			<dd><user id="'.$lc['lc_a_up_id'].'" /></dd>' : '').'
			<dt>Tidspunkt</dt>
			<dd>'.ess::$b->date->get($lc['lc_time'])->format(date::FORMAT_SEC).'</dd>
		</dl>
		<p>'.crewlog::make_summary($lc, $data).'</p>'.($lc['lc_log'] ? '
		<p><b>Loggmelding:</b></p>
		<div class="p">'.game::format_data($lc['lc_log']).'</div>' : '
		<p>Ingen loggmelding.</p>').'
		<p><b>Data:</b></p>
		<pre>'.htmlspecialchars(print_r($data, true)).'</pre>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Redigere en oppføring
	 */
	protected function handle_specific_edit($lc, $lc_action, $data)
	{
		ess::$b->page->add_title("Redigering av oppføring: {$lc['lc_id']} (".htmlspecialchars($lc_action[4]).")");
		
		// behandle ulike type oppføringer
		switch (crewlog::$actions_id[$lc['lc_lca_id']])
		{
			// advarsel
			case "user_warning":
				$invalidated = !empty($data['invalidated']);
				
				if (!$invalidated)
				{
					// slette?
					if (isset($_POST['revoke']))
					{
						// legg til logg
						$d = array(
							"lc_id" => $lc['lc_id'],
							"type" => $data['type'],
							"priority" => $data['priority']
						);
						crewlog::log("user_warning_invalidated", $lc['lc_a_up_id'], null, $d);
						
						// marker som slettet
						ess::$b->db->query("
							INSERT INTO log_crew_data
							SET lcd_lc_id = {$lc['lc_id']}, lcd_lce_id = 5, lcd_lca_id = {$lc['lc_lca_id']}, lcd_data_int = 1
							ON DUPLICATE KEY UPDATE lcd_data_int = 1");
						
						// har vi en hendelse vi kan slette?
						if (!empty($data['notified_id']))
						{
							ess::$b->db->query("DELETE FROM users_log WHERE id = {$data['notified_id']}");
						}
						
						ess::$b->page->add_message("Advarselen ble markert som ugyldig.");
						redirect::handle("crewlogg?lc_id={$lc['lc_id']}");
					}
					
					// redigere?
					if (isset($_POST['edit']))
					{
						$types = crewlog::$user_warning_types;
						
						$log = trim(postval("log"));
						$note = trim(postval("note"));
						$type = postval("type");
						$priority = (int) postval("priority");
						
						if (empty($log) || empty($note))
						{
							ess::$b->page->add_message("Både begrunnelse og intern informasjon må fylles ut.", "error");
						}
						
						elseif (!isset($types[$type]))
						{
							ess::$b->page->add_message("Ugyldig kategori.", "error");
						}
						
						elseif ($priority < 1 || $priority > 3)
						{
							ess::$b->page->add_message("Ugylig alvorlighet.", "error");
						}
						
						elseif ($priority == $data['priority'] && $log == $lc['lc_log'] && $note == $data['note'] && $types[$type] == $data['type'])
						{
							ess::$b->page->add_message("Ingenting ble endret.", "error");
						}
						
						else
						{
							$d = array(
								"lc_id" => $lc['lc_id']
							);
							
							$d['priority_new'] = $priority;
							if ($priority != $data['priority'])
							{
								$d['priority_old'] = $data['priority'];
							}
							
							$d['type_new'] = $types[$type];
							if ($types[$type] != $data['type'])
							{
								$d['type_old'] = $data['type'];
							}
							
							if ($log != $lc['lc_log'])
							{
								$d['log_old'] = $lc['lc_log'];
								$d['log_new'] = $log;
							}
							
							if ($note != $data['note'])
							{
								$d['note_old'] = $data['note'];
								$d['note_new'] = $note;
							}
							
							// legg til at advarselen er redigert
							crewlog::log("user_warning_edit", $lc['lc_a_up_id'], null, $d);
							
							// oppdater crewloggen
							ess::$b->db->query("UPDATE log_crew SET lc_log = ".ess::$b->db->quote($log)." WHERE lc_id = {$lc['lc_id']}");
							ess::$b->db->query("UPDATE log_crew_data SET lcd_data_int = $priority WHERE lcd_lc_id = {$lc['lc_id']} AND lcd_lce_id = 3");
							ess::$b->db->query("UPDATE log_crew_data SET lcd_data_text = ".ess::$b->db->quote($types[$type])." WHERE lcd_lc_id = {$lc['lc_id']} AND lcd_lce_id = 1");
							ess::$b->db->query("UPDATE log_crew_data SET lcd_data_text = ".ess::$b->db->quote($note)." WHERE lcd_lc_id = {$lc['lc_id']} AND lcd_lce_id = 2");
							
							// har vi en hendelse vi kan oppdatere?
							if (!empty($data['notified_id']))
							{
								ess::$b->db->query("UPDATE users_log SET note = ".ess::$b->db->quote(urlencode($types[$type]).":".urlencode($log))." WHERE id = {$data['notified_id']}");
							}
							
							ess::$b->page->add_message("Advarselen ble redigert.");
							redirect::handle("crewlogg?lc_id={$lc['lc_id']}");
						}
					}
				}
				
				echo '
<div class="bg1_c small">
	<h1 class="bg1">Rediger advarsel<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="crewlogg?lc_id='.$lc['lc_id'].'">&laquo; Tilbake</a></p>
	<div class="bg1">
		<dl class="dd_right">
			<dt>Logg ID</dt>
			<dd>'.$lc['lc_id'].'</dd>
			<dt>Handling</dt>
			<dd>'.htmlspecialchars(crewlog::$actions_groups[$lc_action[1]]).': '.htmlspecialchars($lc_action[4]).'</dd>
			<dt>Utført av</dt>
			<dd><user id="'.$lc['lc_up_id'].'" /></dd>'.($lc['lc_a_up_id'] ? '
			<dt>Påvirket spiller</dt>
			<dd><user id="'.$lc['lc_a_up_id'].'" /></dd>' : '').'
			<dt>Tidspunkt</dt>
			<dd>'.ess::$b->date->get($lc['lc_time'])->format(date::FORMAT_SEC).'</dd>
		</dl>
		<dl class="dd_right">
			<dt>Kategori</dt>
			<dd>'.$data['type'].'</dd>
			<dt>Prioritet</dt>
			<dd>'.($data['priority'] == 1 ? 'Lav' : ($data['priority'] == 3 ? 'Høy' : 'Moderator')).'</dd>
			<dt>Varslet?</dt>
			<dd>'.(empty($data['notified']) ? 'Brukeren ble ikke varslet' : 'Brukeren ble varslet med logg').'</dd>'.($invalidated ? '
			<dt>Ugyldig</dt>
			<dd><b>Advarselen er trukket tilbake</b></dd>' : '').'
		</dl>
		<p>Begrunnelse:</p>
		<div class="crewlog_note">'.game::format_data($lc['lc_log']).'</div>
		<p>Intern informasjon:</p>
		<div class="crewlog_note">'.game::format_data($data['note']).'</div>';
				
				if ($invalidated)
				{
					echo '
		<p>Denne advarselen er trukket tilbake og kan ikke redigeres.</p>';
				}
				
				else
				{
					echo '
		<form action="" method="post">
			<div class="hr"></div>
			<p><b>Rediger advarsel:</b></p>
			<dl class="dd_right">
				<dt>Kategori</dt>
				<dd>
					<select name="type">';
			
			$type = array_search($data['type'], crewlog::$user_warning_types);
			if (isset($_POST['type']) && isset($types[$_POST['type']])) $type = (int) $_POST['type'];
			if ($type === false) echo '
							<option value="">Velg ..</option>';
			
			foreach (crewlog::$user_warning_types as $key => $row)
			{
				echo '
						<option value="'.$key.'"'.($key === $type ? ' selected="selected"' : '').'>'.htmlspecialchars($row).'</option>';
			}
			
			echo '
					</select>
				</dd>
				<dt>Alvorlighet/prioritet</dt>
				<dd>
					<select name="priority">';
			
			$priority = isset($_POST['priority']) && is_numeric($_POST['priority']) && $_POST['priority'] >= 1 && $_POST['priority'] <= 3 ? $_POST['priority'] : $data['priority'];
			echo '
						<option value="1"'.($priority == 1 ? ' selected="selected"' : '').'>Lav</option>
						<option value="2"'.($priority == 2 ? ' selected="selected"' : '').'>Moderat</option>
						<option value="3"'.($priority == 3 ? ' selected="selected"' : '').'>Høy</option>
					</select>
				</dd>
			</dl>
			<p>Begrunnelse:</p>
			<p><textarea name="log" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(postval("log", $lc['lc_log'])).'</textarea></p>
			<p>Intern informasjon:</p>
			<p><textarea name="note" rows="10" cols="30" style="width: 98%">'.htmlspecialchars(postval("note", $data['note'])).'</textarea></p>
			<p class="c">'.show_sbutton("Oppdater advarsel", 'name="edit"').'</p>
		</form>
		<form action="" method="post">
			<p class="c">'.show_sbutton("Trekk tilbake advarsel", 'name="revoke" onclick="return confirm(\'Er du sikker på at du ønsker å trekke tilbake denne advarselen?\')"').'</p>
		</form>';
				}
				
				echo '
	</div>
</div>';
			break;
			
			// ukjent
			default:
				ess::$b->page->add_message("Kan ikke redigere denne oppføringen.", "error");
				redirect::handle("crewlogg?lc_id={$lc['lc_id']}");
		}
		
		ess::$b->page->load();
	}
	
	protected function show()
	{
		ess::$b->page->add_css('
#crewlog_filter { padding: 0 10px }
#crewlog_filter td { font-size: 10px }');
		
		ess::$b->page->add_js_domready('
	// vise/skjule filter
	$("crewlog_filter_a").addEvent("click", function()
	{
		var elm = $("crewlog_filter");
		if (elm.hasClass("hide"))
		{
			// vis skjemaet
			elm.removeClass("hide");
		}
		else
		{
			// skjul skjemaet
			elm.addClass("hide");
		}
	});');
		
		echo '
<h1>Crewets handlinger - crewlogg</h1>
<form action="" method="post">
	<div class="bg1_c large">
		<h1 id="crewlog_filter_a" class="bg1 pointer">Filteralternativer<span class="left"></span><span class="right"></span></h1>
		<div id="crewlog_filter" class="bg1'.(!$this->filters_active ? ' hide' : '').'">';
		
		$this->show_filters();
		
		echo '
			<p class="c">'.show_sbutton("Oppdater").'</p>
		</div>
	</div>';
		
		if ($this->filter_by_up || $this->filter_a_up || $this->filter_time_before || $this->filter_time_after)
		{
			echo '
	<div class="section center" style="width: 50%">
		<h2>Filter</h2>
		<ul>';
			
			$list_users = function($list)
			{
				$d = array();
				foreach ($list as $r) $d[] = '<user="'.htmlspecialchars($r).'" />';
				return sentences_list($d, ", ", " eller ");
			};
			
			if ($this->filter_by_up)
			{
				echo '
			<li>'.(isset($_POST['by_invert']) ? 'Ikke u' : 'U').'tført av: '.$list_users($this->filter_by_up).'</li>';
			}
			
			if ($this->filter_a_up)
			{
				echo '
			<li>'.(isset($_POST['a_invert']) ? 'Ikke s' : 'S').'piller berørt: '.$list_users($this->filter_a_up).'</li>';
			}
			
			if ($this->filter_time_before && !$this->filter_time_after)
			{
				echo '
			<li>Før/lik '.ess::$b->date->get($this->filter_time_before)->format().'</li>';
			}
			
			elseif (!$this->filter_time_before && $this->filter_time_after)
			{
				echo '
			<li>Etter/lik '.ess::$b->date->get($this->filter_time_after)->format().'</li>';
			}
			
			elseif ($this->filter_time_before && $this->filter_time_after)
			{
				echo '
			<li>Mellom/lik '.ess::$b->date->get($this->filter_time_after)->format().' og '.ess::$b->date->get($this->filter_time_before)->format().'</li>';
			}
			
			echo '
	</div>';
		}
		
		$this->show_logs();
		
		echo '
</form>';
	}
	
	protected function load_actions()
	{
		// sett opp gruppert med gruppe
		$this->actions = array();
		
		foreach (crewlog::$actions_groups as $group_id => $name)
		{
			$this->actions[$group_id] = array(
				"name" => $name,
				"actions" => array()
			);
		}
		
		foreach (crewlog::$actions as $name => $row)
		{
			if (!isset($this->actions[$row[1]])) throw new HSException("Mangler gruppe.");
			
			// syntax: action => array(lca_id, lcg_id, have_a_up_id, have_log, description, data array(data id => array(data name, data type, data summary, data optional), ...))
			$row['name'] = $name;
			$this->actions[$row[1]]['actions'][$row[0]] = $row;
			$this->actions_all[] = $row[0];
		}
	}
	
	/**
	 * Sjekk hvilke filter som skal være aktivert, om noen
	 */
	protected function check_filters()
	{
		$this->check_filters_actions();
		$this->check_filters_by_up();
		$this->check_filters_a_up();
		$this->check_filters_time();
	}
	
	protected function check_filters_actions()
	{
		if (!isset($_POST['f']) || !is_array($_POST['f']))
		{
			$this->filter_actions = null;
			return;
		}
		$this->filter_actions = array();
		
		foreach ($_POST['f'] as $id => $dummy)
		{
			if (!in_array($id, $this->actions_all)) continue;
			$this->filter_actions[] = (int) $id;
		}
		
		if (count($this->actions_all) == count($this->filter_actions)) $this->filter_actions = null;
		if ($this->filter_actions) $this->filters_active = true;
	}
	
	protected function check_filters_by_up()
	{
		if (!isset($_POST['by']) || empty($_POST['by']))
		{
			$this->filter_by_up = null;
			return;
		}
		
		$this->filter_by_up = array_unique(preg_split("/ *, */", $_POST['by']));
		
		if (count($this->filter_by_up) == 0) $this->filter_by_up = null;
		else $this->filters_active = true;
	}
	
	protected function check_filters_a_up()
	{
		if (!isset($_POST['a']) || empty($_POST['a']))
		{
			$this->filter_a_up = null;
			return;
		}
		
		$this->filter_a_up = array_unique(preg_split("/ *, */", $_POST['a']));
		
		if (count($this->filter_a_up) == 0) $this->filter_a_up = null;
		else $this->filters_active = true;
	}
	
	protected function check_filters_time()
	{
		$this->filter_time_before = null;
		$this->filter_time_after = null;
		
		if (isset($_POST['before']) && !empty($_POST['before']))
		{
			$time = strtotime($_POST['before']);
			if ($time) $this->filter_time_before = $time;
		}
		
		if (isset($_POST['after']) && !empty($_POST['after']))
		{
			$time = strtotime($_POST['after']);
			if ($time) $this->filter_time_after = $time;
		}
	}
	
	/**
	 * Vis filteralternativer
	 */
	protected function show_filters()
	{
		$cols = array();
		$rows_max = 0;
		foreach ($this->actions as $group_id => $data)
		{
			$rows = array();
			
			$rows[] = '
				<th class="pointer box_handle_toggle" rel="f'.$group_id.'[]">'.$data['name'].'</th>';
			
			foreach ($data['actions'] as $action)
			{
				$checked = !$this->filter_actions || in_array($action[0], $this->filter_actions) ? ' checked="checked"' : '';
				
				$rows[] = '
				<td class="box_handle"><input type="checkbox" name="f['.$action[0].']" rel="f'.$group_id.'[]" value=""'.$checked.' />'.htmlspecialchars($action[4]).'</td>';
			}
			
			$cols[] = $rows;
			$rows_max = max($rows_max, count($rows));
		}
		
		echo '
	<table class="table tablem center" width="100%">
		<tbody>';
		
		for ($i = 0; $i < $rows_max; $i++)
		{
			echo '
			<tr>';
			
			for ($e = 0; $e < count($cols); $e++)
			{
				if (!isset($cols[$e][$i]))
				{
					echo '
				<td>&nbsp;</td>';
				}
				
				else
				{
					echo $cols[$e][$i];
				}
			}
			
			echo '
			</tr>';
		}
		
		echo '
		</tbody>
	</table>
	<dl class="center w300 dd_right">
		<dt>Utført av</dt>
		<dd><input type="checkbox" name="by_invert"'.(isset($_POST['by_invert']) ? ' checked="checked"' : '').' id="by_invert"><label for="by_invert"> inverter</label> <input type="text" class="styled w120" name="by" value="'.($this->filter_by_up ? implode(", ", $this->filter_by_up) : '').'" /></dd>
		<dt>Spiller berørt</dt>
		<dd><input type="checkbox" name="a_invert"'.(isset($_POST['a_invert']) ? ' checked="checked"' : '').' id="a_invert"><label for="a_invert"> inverter</label> <input type="text" class="styled w120" name="a" value="'.($this->filter_a_up ? implode(", ", $this->filter_a_up) : '').'" /></dd>
		<dt>Før/lik tidspunkt</dt>
		<dd><input type="text" class="styled w120" name="before" value="'.(!$this->filter_time_before ? '' : ess::$b->date->get($this->filter_time_before)->format("d.m.Y H:i:s")).'" /></dd>
		<dt>Etter/lik tidspunkt</dt>
		<dd><input type="text" class="styled w120" name="after" value="'.(!$this->filter_time_after ? '' : ess::$b->date->get($this->filter_time_after)->format("d.m.Y H:i:s")).'" /></dd>
	</dl>';
	}
	
	protected function get_full_up_list($ups)
	{
		$result = ess::$b->db->query("
			SELECT DISTINCT up2.up_id
			FROM users_players up1
				JOIN users ON up1.up_u_id = u_id
				JOIN users_players up2 ON up2.up_u_id = u_id
			WHERE up1.up_name IN (".implode(",", array_map(array(ess::$b->db, "quote"), $ups)).")");
		
		$ids = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$ids[] = $row['up_id'];
		}
		
		if (count($ids) == 0) $ids[] = 0;
		return implode(",", $ids);
	}
	
	/**
	 * Vis logger
	 */
	protected function show_logs()
	{
		$filters = "";
		if ($this->filter_actions) $filters .= " AND lc_lca_id IN (".implode(",", $this->filter_actions).")";
		if ($this->filter_time_before) $filters .= " AND lc_time <= {$this->filter_time_before}";
		if ($this->filter_time_after) $filters .= " AND lc_time >= {$this->filter_time_after}";
		
		if ($this->filter_by_up) $filters .= " AND lc_up_id".(isset($_POST['by_invert']) ? ' NOT' : '')." IN (".$this->get_full_up_list($this->filter_by_up).")";
		if ($this->filter_a_up) $filters .= " AND lc_a_up_id".(isset($_POST['a_invert']) ? ' NOT' : '')." IN (".$this->get_full_up_list($this->filter_a_up).")";
		
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::ACTIVE_POST, "side", pagei::PER_PAGE, 100);
		$result = $pagei->query("
			SELECT lc_id, lc_up_id, lc_time, lc_lca_id, lc_a_up_id, lc_log
			FROM log_crew
			WHERE 1$filters
			ORDER BY lc_time DESC");
		$rows = array();
		while ($row = mysql_fetch_assoc($result)) $rows[$row['lc_id']] = $row;
		
		$data = crewlog::load_summary_data($rows);
		
		$logs = array();
		foreach ($data as $row)
		{
			// hent sammendrag
			$summary = crewlog::make_summary($row);
			$day = ess::$b->date->get($row['lc_time'])->format(date::FORMAT_NOTIME);
			
			$logs[$day][] = '<p><span class="time"><a href="'.ess::$s['relative_path'].'/crew/crewlogg?lc_id='.$row['lc_id'].'">'.ess::$b->date->get($row['lc_time'])->format("H:i").'</a>:</span> '.$summary.'</p>';
		}
		
		ess::$b->page->add_css('
		h1.crewlog { margin: 30px auto 20px }
		div.crewlog { margin: 0 30px }
		.crewlog .time { color: #888888; padding-right: 5px }
		');
		
		if ($this->filters_active)
		{
			echo '
		<p class="c">Fant '.fwords("%d oppføring", "%d oppføringer", $pagei->total).' som passet til filteret.</p>';
		}
		
		echo '
		<p class="c">'.$pagei->pagenumbers($this->filters_active ? "input" : null).'</p>
		<div class="crewlog">';
		
		foreach ($logs as $day => $items)
		{
			echo '
			<div class="bg1_c">
				<h1 class="bg1">'.$day.'<span class="left2"></span><span class="right2"></span></h1>
				<div class="bg1">
					'.implode('
					', $items).'
				</div>
			</div>';
		}
		
		echo '
			<p class="c">'.$pagei->pagenumbers($this->filters_active ? "input" : null).'</p>
		</div>';
	}
}