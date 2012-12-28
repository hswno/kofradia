<?php

define("ALLOW_GUEST", true);
require "base.php";

class page_hall_of_fame
{
	public function __construct()
	{
		ess::$b->page->add_title("Hall of Fame");
		kf_menu::page_id("hall_of_fame");
		
		$data = hall_of_fame::get_all_status();
		$this->css();
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Hall of Fame<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1">';
		
		foreach ($data as $name => $group)
		{
			echo '
		<div class="hof_group">';
			
			switch ($name)
			{
				case "rank": echo '
			<p class="hof_desc">Første spiller til å oppnå rangering:</p>'; break;
				case "rank_kill": echo '
			<p class="hof_desc">Første spiller til å drepe en rangert spiller:</p>'; break;
				case "ff_owner": echo '
			<p class="hof_desc">Første spiller til å eie:</p>'; break;
				case "cash_num": echo '
			<p class="hof_desc">Første spiller til å oppnå pengerangering:</p>'; break;
				case "familie": echo '
			<p class="hof_desc">Første broderskap i spillet:</p>'; break;
				case "familie_rank": echo '
			<p class="hof_desc">Høyest rangert broderskap i spillet:</p>'; break;
			}
			
			foreach ($group as $id => $info)
			{
				$time = $info ? ' <span class="hof_time">'.ess::$b->date->get($info[0])->format(date::FORMAT_NOTIME).'</span>' : '';
				$subject = $info ? hall_of_fame::get_subject_html($name, $info[1]) : 'Ikke oppnådd';
				$text = $this->get_text($name, $id, $info[1]);
				
				echo '
			<p>'.$text.' <span class="hof_subject">'.$subject.$time.'</span></p>';
			}
			
			echo '
		</div>';
		}
		
		echo '
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	protected function get_text($name, $sub, $data)
	{
		switch ($name)
		{
			case "rank":
			case "rank_kill":
				return ucfirst(game::$ranks['items_number'][$sub]['name']);
			
			case "familie":
				return 'Broderskap';
			
			case "familie_rank":
				return game::format_num($data['ff_points_sum']).' poeng';
				
			case "ff_owner":
				return ucfirst(ff::$types[$sub]['typename']);
			
			case "cash_num":
				return hall_of_fame::get_cash_pos($sub);
		}
	}
	
	protected function css()
	{
		ess::$b->page->add_css('
.hof_group {
	margin: 10px 0 20px;
}
.hof_group:last-child {
	margin-bottom: 10px;
}
.hof_group p {
	margin: 5px 0 5px 10px;
	overflow: hidden;
}
p.hof_desc {
	color: #AAA;
	margin-left: 0;
}
.hof_subject {
	float: right;
	min-width: 200px;
	color: #666;
}
.hof_time {
	color: #666;
	float: right;
}
	');
	}
}

new page_hall_of_fame();