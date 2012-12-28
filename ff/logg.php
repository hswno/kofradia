<?php

require "../base.php";

new page_ff_log();
class page_ff_log
{
	/**
	 * FF
	 * @var ff
	 */
	public $ff;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needaccess(true);
		
		ess::$b->page->add_title("Logg");
		$this->show();
		
		$this->ff->load_page();
	}
	
	/**
	 * Vis logg
	 */
	protected function show()
	{
		$ff_reset = $this->ff->data['ff_time_reset'] && !$this->ff->mod ? " AND ffl_time > {$this->ff->data['ff_time_reset']}" : "";
		
		// finn ut hva som er tilgjengelig
		$result = ess::$b->db->query("SELECT DISTINCT ffl_type FROM ff_log WHERE ffl_ff_id = {$this->ff->id}$ff_reset");
		$in_use = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$in_use[] = $row['ffl_type'];
		}
		
		$tilgjengelig = array();
		foreach (ff::$log_id as $id => $name)
		{
			if (in_array($id, $in_use)) $tilgjengelig[] = $id;
		}
		
		$i_bruk = $tilgjengelig;
		
		// filter
		$filter = array();
		$matches = false;
		foreach ($_GET as $name => $val)
		{
			if (preg_match("/^f([0-9]+)$/D", $name, $matches) && in_array($matches[1], $tilgjengelig))
			{
				$filter[] = $matches[1];
			}
		}
		if (count($filter) == 0) $filter = false;
		else
		{
			$i_bruk = $filter;
			$filter = true;
			
			ess::$b->page->add_message("Du har aktivert et filter og viser kun bestemte enheter.");
		}
		
		
		if ($filter)
		{
			ess::$b->page->add_css('.filter_inactive { display: none }');
		}
		else
		{
			ess::$b->page->add_css('.filter_active { display: none }');
		}
		
		if (count($tilgjengelig) > 0)
		{
			echo '
<form action="" method="get">
	<input type="hidden" name="ff_id" value="'.$this->ff->id.'" />
	<div class="section" style="width: 400px" id="filteroptions">
		<h2>Filter</h2>
		<p class="h_right">
			<span class="logg_filters filter_active"><a href="#" class="box_handle_toggle" rel="f[]">Merk alle</a> <a href="javascript:void(0)" onclick="toggle_display(\'.logg_filters\', event)">Skjul filteralternativer</a></span>
			<span class="logg_filters filter_inactive"><a href="#" onclick="toggle_display(\'.logg_filters\', event)">Vis filteralternativer</a></span>
		</p>
		<div class="logg_filters filter_active">
			<table class="table center tablemt" width="100%">
				<tbody>';
			
			$tbody = new tbody(min(3, count($tilgjengelig))); // 3 kolonner
			foreach ($tilgjengelig as $id)
			{
				$title = ff::$log[ff::$log_id[$id]][1];
				$aktivt = in_array($id, $i_bruk) && $filter;
				$tbody->append('<input type="checkbox" name="f'.$id.'" rel="f[]" value=""'.($aktivt ? ' checked="checked"' : '').' />'.htmlspecialchars($title), 'class="box_handle"');
			}
			$tbody->clean();
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Oppdater").'</p>
		</div>
	</div>
</form>';
		}
		
		$where = $ff_reset;
		if ($filter)
		{
			$where .= ' AND ffl_type IN ('.implode(",", $i_bruk).')';
		}
		
		// sideinformasjon - hent radene på denne siden
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 30);
		$result = $pagei->query("SELECT SQL_CALC_FOUND_ROWS ffl_id, ffl_time, ffl_type, ffl_data, ffl_extra FROM ff_log WHERE ffl_ff_id = {$this->ff->id}$where ORDER BY ffl_time DESC, ffl_id DESC");
		
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">
	Ingen logg meldinger ble funnet.
</p>';
		}
		
		else
		{
			// css
			ess::$b->page->add_css('
.ffl_time {
	color: #AAA;
}');
			
			// logg meldingene
			$logs = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$day = ess::$b->date->get($row['ffl_time'])->format(date::FORMAT_NOTIME);
				$data = $this->ff->format_log($row['ffl_id'], $row['ffl_time'], $row['ffl_type'], $row['ffl_data'], $row['ffl_extra']);
				
				$logs[$day][] = '<span class="ffl_time">'.ess::$b->date->get($row['ffl_time'])->format("H:i").':</span> '.$data;
			}
			
			foreach ($logs as $day => $items)
			{
				echo '
<div class="section" style="width: 400px">
	<h2>'.$day.'</h2>';
				
				foreach ($items as $item)
				{
					echo '
	<p>'.$item.'</p>';
				}
				
				echo '
</div>';
			}
			
			echo '
<p class="c">
	Viser '.$pagei->count_page.' av '.$pagei->total.' logg melding'.($pagei->total == 1 ? '' : 'er');
			
			if ($pagei->pages > 1)
			{
				echo '<br />
	'.$pagei->pagenumbers(game::address("logg", $_GET, array("side"))."#logg", game::address("logg", $_GET, array("side"), array("side" => "_pageid_"))."#logg");
			}
			
			echo '
</p>';
		}
	}
}