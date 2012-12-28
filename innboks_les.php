<?php

require "base.php";

class page_innboks_les
{
	/**
	 * Meldingstråden
	 * @var inbox_thread
	 */
	protected $thread;
	
	/**
	 * Side
	 * @var pagei
	 */
	protected $pagei;
	
	protected $can_reply;        // kan vi svare på meldingen?
	protected $num_messages;
	protected $highlight_im_id;
	protected $per_page = 15;    // antall meldinger per side
	protected $new;
	protected $limit;            // hvor mange som skal vises på akkurat denne siden
	
	public function __construct()
	{
		ess::$b->page->add_title("Meldinger");
		
		// finn meldingstråden
		$this->thread = inbox_thread::get(getval("id"));
		if (!$this->thread)
		{
			ess::$b->page->add_message("Fant ikke meldingstråden.", "error");
			redirect::handle("innboks");
		}
		
		$this->thread->check_rel();
		$this->thread->get_receivers();
		$this->can_reply = $this->thread->can_reply_access && $this->thread->can_reply_receivers;
		
		redirect::store("innboks_les?id={$this->thread->id}");
		
		$this->check_actions();
		
		// oppdater stats og teller
		$this->thread->stats_view_update();
		$this->thread->counter_new_reset();
		
		// finn ut hvor mange meldinger vi kan se
		$this->num_messages = $this->thread->num_messages();
		
		// sett opp sidenummer
		$this->set_page_info();
		
		// antall nye meldinger
		$this->new = $this->thread->data_rel ? $this->thread->data_rel['ir_unread'] : 0;
		
		// skrive svar?
		if (isset($_GET['reply']) && $this->can_reply)
		{
			ess::$b->page->add_js_domready('
			$("textContent").focus();
			$("default_main").goto();');
		}
		
		// vis meldingen
		$this->show();
	}
	
	protected function set_page_info()
	{
		// sett opp side informasjon
		$this->pagei = new pagei(pagei::TOTAL, $this->num_messages, pagei::ACTIVE_GET, "side", pagei::PER_PAGE, $this->per_page);
		$this->limit = $this->pagei->per_page;
		
		// har vi nye meldinger?
		if ($this->thread->data_rel && $this->thread->data_rel['ir_unread'] > 0)
		{
			$this->pagei->__construct(pagei::ACTIVE, 1);
			$this->limit = max($this->limit, $this->thread->data_rel['ir_unread']);
		}
		
		// skal vi gå til en bestemt melding?
		elseif (isset($_GET['goto']))
		{
			$im_id = intval(getval("goto"));
			
			// forsøk å finn meldingen
			$ant = $this->thread->message_locate($im_id);
			if (!$ant)
			{
				ess::$b->page->add_message("Fant ingen melding med ID $im_id.", "error");
				redirect::handle();
			}
			
			// finn ut hvilken side vi skal til
			$side = ceil($ant/$this->per_page);
			
			// gå til korrekt side
			if ($this->pagei->active != $side)
			{
				redirect::handle("innboks_les?id={$this->thread->id}&goto=$im_id&side=$side");
			}
			
			$this->highlight_im_id = $im_id;
		}
	}
	
	protected function load_page()
	{
		$data = @ob_get_contents(); @ob_clean();
		
		echo '
<div class="page_w0">'.$data.'
</div>';
		
		ess::$b->page->load();
	}
	
	protected function check_actions()
	{
		// svare?
		if (isset($_GET['reply']) && $this->thread->reply_test())
		{
			if (isset($_POST['melding']) && isset($_POST['post']) && $this->thread->reply_test_wait())
			{
				// forsøk å legge til svaret
				$this->thread->reply_add($_POST['melding']);
			}
		}
		
		// slette svar?
		if (isset($_GET['im_del']))
		{
			$this->thread->reply_delete_try();
		}
		
		// gjenopprette svar?
		if (isset($_GET['im_restore']))
		{
			$this->thread->reply_restore_try();
		}
		
		// slette hele meldingstråden?
		if (isset($_POST['slettalle']) && !$this->thread->restrict)
		{
			$this->thread->delete();
		}
	}
	
	protected function add_css()
	{
		ess::$b->page->add_css('
.im_tools.top a { margin-right: 10px }
.im_tools.bottom { float: right; margin: -3px 0 10px 0 }
.im_tools.bottom.left { float: left }
');
	}
	
	protected function add_js($last_id)
	{
		// sørg for at rapporteringslenkene blir prosessert
		ess::$b->page->add_js('sm_scripts.report_links();');
		
		ess::$b->page->add_js_domready('
	if ($("im_deltakere"))
	{
		var t = $("im_deltakere_det").set("tween", {duration: 150});
		var e = $("im_deltakere_i");
		var b = new FBox();
		b.options.delay = 50;
		b.pos_x = "left"; b.pos_y = "top";
		b.rel_x = b.rel_y = e;
		b.offset_x = 5; b.offset_y = ["height", 5];
		b.connect(e, true, false);
		b.create_box(t.dispose());
		b.autoclose();
	}');
		
		if ($this->thread->data_rel)
		{
			ess::$b->page->add_css('.icon2 { margin-left: 5px; line-height: 2px; vertical-align: bottom }');
			ess::$b->page->add_js_domready('
	var marked = '.($this->thread->data_rel['ir_marked'] != 0 ? 'true' : 'false').';
	$("im_mark").set("html", \'<input type="checkbox" id="im_mark_b" /><label for="im_mark_b"> Marker for oppfølging</label>\');
	var xhr, b = $("im_mark").getElement("input");
	b.set("checked", marked);
	
	var loader = new Element("span", { "text": " (lagrer..)" }).inject($("im_mark")).fade("hide");
	loader.start = function()
	{
		this.fade("show");
		this.set("text", " (lagrer..)");
	};
	loader.end = function(error)
	{
		this.set("text", error ? " (feilet..)" : " (OK)");
		this.fade("out").get("tween").addChain(function(){this.fade("in");});
	};
	
	b.addEvent("click", function()
	{
		if (xhr) xhr.cancel();
		else xhr = new Request({url: relative_path + "/ajax/inbox?it='.$this->thread->id.'&a=mark"}).addEvents({
			"success": function(text)
			{
				// stopp laster
				loader.end();
				
				// markert
				if (text == "MARK-TRUE") b.set("checked", true);
				else if (text == "MARK-FALSE") b.set("checked", false);
				else
				{
					alert("Ukjent respons: " + text);
				}
			},
			"failure": function(xhr)
			{
				// stopp laster
				loader.end(true);
				
				alert(xhr.respone);
			}
		});
		loader.start();
		xhr.send({
			data: { "sid": User.s_id, "mark": this.get("checked") ? 1 : 0 }
		});
	});
	
	// informasjonsboks
	(function(){
		var b = new FBox(), w = $("im_mark");
		b.options.delay = 0;
		b.pos_x = "left"; b.pos_y = "top";
		b.rel_x = b.rel_y = w;
		b.offset_x = 5; b.offset_y = ["height", 5];
		b.connect(w, true, false);
		b.create_box();
		b.populate(\'<p class="info_box" style="margin: 5px 2px; font-weight: 10px; max-width: 200px">Ved å merke av for denne boksen blir meldingstråden alltid sortert øverst i innboksen.</p>\');
	})();');
		}
		
		// hent javascript filen til innboksen
		if ($this->thread->can_reply_receivers)
		{
			ess::$b->page->add_js_file(ess::$s['relative_path']."/js/innboks.js");
			ess::$b->page->add_js_domready('
	new InboxMessage('.$this->thread->id.', '.$last_id.', '.($this->pagei->active == 1 ? 'true' : 'false').');');
		}
	}
	
	protected function add_receivers()
	{
		if (count($this->thread->receivers) == 0) return;
		
		echo '
<div id="im_deltakere_det">
	<table class="table">
		<thead>
			<tr>
				<th>Spiller</th>
				<th>Antall</th>
				<th>Uleste</th>'.(access::has("mod") ? '
				<th>Vis</th>' : '').'
				<th>Status</th>
			</tr>
		</thead>
		<tbody>';
		
		foreach($this->thread->receivers as $row)
		{
			echo '
			<tr>
				<td><user id="'.$row['ir_up_id'].'" /></td>
				<td class="r">'.$row['num_messages'].'</td>
				<td class="r">'.($row['ir_unread'] > 0 ? '<b>'.$row['ir_unread'].'</b>' : $row['ir_unread']).'</td>'.(access::has("mod") ? '
				<td>'.$row['ir_views'].'</td>' : '').'
				<td>'.($row['up_access_level'] == 0 ? '<span class="dark">Død'.(access::has("crewet") && $row['u_access_level'] != 0 && $row['u_active_up_id'] == $row['ir_up_id'] ? ', men bruker aktiv' : '').'</span>' : ($row['ir_deleted'] != 0 ? '<span class="dark">Slettet meldingen</span>' : 'Mottar nye meldinger')).'</td>
			</tr>';
		}
		
		echo '
		</tbody>
	</table>
</div>';
		
	}
	
	protected function show()
	{
		// tittel på meldingstråden
		ess::$b->page->add_title($this->thread->data_thread['it_title']);
		
		// sett opp deltakere
		$deltakere = array();
		$c = access::has("crewet");
		foreach ($this->thread->receivers as $row)
		{
			if ($this->thread->data_rel && $this->thread->data_rel['ir_up_id'] == $row['ir_up_id']) continue;
			$p = $row['ir_deleted'] != 0 || ($row['up_access_level'] == 0 && (!access::has("crewet") || $row['u_access_level'] == 0 || $row['u_active_up_id'] != $row['ir_up_id']));
			$deltakere[] = ($p ? '<span class="user_strike">' : '').'<user id="'.$row['ir_up_id'].'" />'.($p ? '</span>' : '');
		}
		$deltakere_siste = array_pop($deltakere);
		
		$this->add_receivers();
		
		// overskrift
		if ($this->thread->restrict || !$this->thread->can_reply_receivers)
		{
			echo '
<h1>Melding: '.htmlspecialchars($this->thread->data_thread['it_title']).'</h1>';
		}
		else
		{
			echo '
<form action="" method="post">
	<h1><span class="red">'.show_sbutton("Slett", 'name="slettalle" onclick="return confirm(\'Dette vil slette meldingstråden for alle deltakere. Denne handlingen kan ikke angres uten videre. Fortsette?\')"').'</span> Melding: '.htmlspecialchars($this->thread->data_thread['it_title']).'</h1>
</form>';
		}
		
		// tittel og verktøy
		echo '
<form action="innboks" method="post">
	<p class="im_tools top h_right">
		<a href="innboks'.($this->thread->data_rel ? '' : '?user='.urlencode($this->thread->data_rel['up_name'])).'">Tilbake til meldinger</a>
		<input type="hidden" name="it_id[]" value="'.$this->thread->id.'" />'.(!isset($_GET['reply']) && $this->can_reply ? '
		'.show_sbutton("Opprett svar", 'name="reply" accesskey="s"', 'reply_link_form_show') : '').($this->thread->data_rel ? '
		<span class="red">'.show_sbutton("Slett", 'name="slett"  accesskey="d" onclick="return confirm(\'Er du sikker på at du vil slette meldingen?\')"').'</span>' : '').'
	</p>
</form>';
		
		// deltakere
		if ($deltakere_siste)
		{
			echo '
<p id="im_deltakere"><span id="im_deltakere_i">Deltakere: '.(count($deltakere) > 0 ? implode(", ", $deltakere).' og ' : '').$deltakere_siste.'</span></p>';
			
			if (!$this->thread->can_reply_access)
			{
				echo '
<p>Du har ikke mulighet til å svare i denne meldingen.</p>';
			}
			elseif (!$this->thread->can_reply_receivers)
			{
				echo '
<p>Det er ingen mottakere du kan svare til.</p>';
			}
		}
		else
		{
			echo '
<p>Det er ingen andre deltakere enn deg selv i denne meldingstråden.</p>';
		}
		
		// flere sider?
		if ($this->pagei->pages > 1)
		{
			echo '
<p class="c">'.$this->pagei->pagenumbers(array("goto")).'</p>';
		}
		
		// svarskjema
		echo '
<div id="container_reply"'.(!isset($_GET['reply']) ? ' style="display: none"' : '').'>
	<form action="'.htmlspecialchars(game::address(PHP_SELF, $_GET, array(), array("reply" => true))).'" method="post" onsubmit="this.onsubmit=function(){return false;}">
		<div class="section" style="margin-top:0">
			<h3>Skriv svar</h3>
			<dl class="dd_auto_100">
				<dt>Innhold</dt>
				<dd><textarea name="melding" rows="10" cols="75" id="textContent">'.htmlspecialchars(postval("melding")).'</textarea></dd>
				<dt'.(isset($_POST['preview']) && isset($_POST['melding']) ? '' : ' style="display: none"').' id="previewDT">Forhåndsvisning</dt>
				<dd'.(isset($_POST['preview']) && isset($_POST['melding']) ? '' : ' style="display: none"').' id="previewDD">'.(!isset($_POST['melding']) || empty($_POST['melding']) ? 'Tom melding?!' : game::bb_to_html($_POST['melding'])).'</dd>
			</dl>
			<h3 class="c">
				'.show_sbutton("Send melding", 'name="post" accesskey="s"').'
				'.show_sbutton("Forhåndsvis", 'name="preview" accesskey="p" id="reply_link_preview"').'
			</h3>
		</div>
	</form>
</div>';
		
		// meldingene
		echo '
<div id="innboks">';
		
		// hent meldingene på denne siden
		$result = $this->thread->get_messages($this->pagei->start, $this->limit);
		
		$i = 0;
		$last_id = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$e = $this->pagei->total - $i - ($this->pagei->active - 1) * $this->pagei->per_page;
			if ($i == 0) $last_id = $row['im_id'];
			
			echo $this->thread->reply_format($row, $e, $this->highlight_im_id == $row['im_id'], $i < $this->new);
			
			$i++;
		}
		
		echo '
</div>';
		
		if ((!isset($_GET['reply']) && $this->can_reply) || $this->thread->data_rel)
		{
			echo '
<form action="innboks" method="post">
	<input type="hidden" name="it_id[]" value="'.$this->thread->id.'" />'.($this->thread->data_rel ? '
	<p class="im_tools bottom left" id="im_mark"></p>' : '').'
	<p class="im_tools bottom">'.(!isset($_GET['reply']) && $this->can_reply ? '
		'.show_sbutton("Opprett svar", 'name="reply" accesskey="s"', 'reply_link_form_show') : '').($this->thread->data_rel ? '
		<span class="red">'.show_sbutton("Slett", 'name="slett"  accesskey="d" onclick="return confirm(\'Er du sikker på at du vil slette meldingen?\')"').'</span>' : '').'
	</p>
</form>';
		}
		
		// flere sider?
		if ($this->pagei->pages > 1)
		{
			echo '
<p class="c center w200">'.$this->pagei->pagenumbers(array("goto")).'</p>';
		}
		
		echo '
<div class="clear"></div>';
		
		$this->add_css();
		$this->add_js($last_id);
		
		$this->load_page();
	}
}

new page_innboks_les();