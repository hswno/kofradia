<?php

require "base.php";

class page_innboks
{
	/**
	 * Innboksen
	 * @var user_innboks
	 */
	protected $inbox;
	
	/**
	 * Vise slettede meldinger?
	 */
	protected $show_deleted = false;
	
	public function __construct()
	{
		ess::$b->page->add_title("Meldinger");
		
		$this->check_reply();
		$this->load_user();
		
		if (isset($_GET['fiks'])) $this->fix_new();
		if (isset($_POST['slett'])) $this->delete_specific();
		if (isset($_POST['slettalle'])) $this->delete_older();
		if (isset($_POST['ps'])) $this->set_per_page();
		
		$this->show_messages();
	}
	
	protected function check_reply()
	{
		// svare på en melding?
		if (isset($_POST['reply']) && isset($_POST['it_id']) && is_array($_POST['it_id']))
		{
			$it_id = postval("it_id");
			$it_id = intval(current($it_id));
			
			redirect::handle("innboks_les?id=$it_id&reply");
		}
	}
	
	protected function load_page()
	{
		if ($this->show_deleted) ess::$b->page->add_message("Du viser meldingene som tilhører ".$this->inbox->u->player->profile_link().".");
		
		$data = @ob_get_contents(); @ob_clean();
		
		echo '
<div class="page_w0">'.$data.'
</div>';
		
		ess::$b->page->load();
	}
	
	protected function load_user()
	{
		$user = login::$user;
		
		// vise en spesiell bruker?
		if (isset($_GET['u_id']) && access::has("admin"))
		{
			// hent bruker
			$user = user::get((int) $_GET['u_id']);
			
			// fant ikke?
			if (!$user)
			{
				echo '
<h1>Meldinger</h1>
<p>Fant ingen bruker med ID <b>'.htmlspecialchars($_GET['u_id']).'</b>.</p>';
				
				$this->load_page();
			}
			
			$this->show_deleted = true;
		}
		
		$this->inbox = new user_innboks($user);
		
		// logg visning av innboks
		putlog("PROFILVIS", "%c5%bVIS-MELDINGER:%b%c %u".login::$user->player->data['up_name']."%u ({$_SERVER['REQUEST_URI']})");
		
		// lagre redirect adresse
		redirect::store($this->addr());
	}
	
	/**
	 * Generer adresse
	 */
	protected function addr($exclude = null, $add = null, $path = null, $get = null)
	{
		$get_default = $path ? array() : $_GET;
		if ($this->show_deleted) $get['u_id'] = $this->inbox->u->id;
		
		return game::address($path ?: "innboks", $get ?: $get_default, $exclude ?: array(), $add ?: array());
	}
	
	/**
	 * Oppdatere spilleren med antall uleste meldinger
	 */
	protected function fix_new()
	{
		$this->inbox->fix_new();
		
		echo '
<h1>Meldinger</h1>
<p class="h_right">
	<a href="innboks_ny">Opprett ny melding</a>
	<a href="'.htmlspecialchars($this->addr(array("utboks", "fiks"), array("alle" => true))).'">Tilbake</a>
</p>
<p>Antall nye meldinger boksen er nå fikset.</p>';
		
		$this->load_page();
	}
	
	/**
	 * Slette enkelttråder
	 */
	protected function delete_specific()
	{
		// ingen merket?
		if (!isset($_POST['it_id']) || !is_array($_POST['it_id']))
		{
			ess::$b->page->add_message("Du må velge noen meldinger du ønsker å slette.", "error");
			redirect::handle();
		}
		
		// hvilke meldingstråder?
		$it = array();
		foreach ($_POST['it_id'] as $id)
		{
			$it[] = intval($id);
		}
		$it = array_unique($it);
		
		// ingen gyldige funnet?
		if (count($it) == 0)
		{
			// ingen meldingstråder
			redirect::handle();
		}
		
		// forsøk å slett
		$deleted = $this->inbox->delete_specific($it);
		if ($deleted > 0)
		{
			// melding
			ess::$b->page->add_message("Du slettet <b>" . $deleted . "</b> meldingstråd" . ($deleted == 1 ? '' : 'er') . ".");
		}
		
		else
		{
			// melding
			ess::$b->page->add_message("Ingen meldinger ble slettet.");
		}
		
		redirect::handle();
	}
	
	/**
	 * Slette alle meldinger eldre enn en dato
	 */
	protected function delete_older()
	{
		// avbryte?
		if (isset($_POST['abort'])) redirect::handle();
		
		// utføre sletting?
		if (isset($_POST['confirm']))
		{
			$this->delete_older_handle();
		}
		
		$this->delete_older_form();
	}
	
	protected function delete_older_handle()
	{
		$dato = postval("dato");
		$tid = postval("tid");
		
		// kontroller dato
		if (!($dato_m = check_date($dato, "%y-%m-%d")))
		{
			ess::$b->page->add_message("Ugyldig dato. Skriv inn i format av yyyy-mm-dd.", "error");
		} 
		
		// kontroller tidspunkt
		elseif (!($tid_m = check_date($tid, "%h:%i")))
		{
			ess::$b->page->add_message("Ugyldig tid. Skriv inn i format av tt:mm.", "error");
		}
		
		else
		{
			// fortsett
			if ($dato_m[1] <= 99)
				$dato_m[1] += 2000;
			
			// tidspunkt
			$date = ess::$b->date->get();
			$date->setTime($tid_m[1], $tid_m[2], 0);
			$date->setDate($dato_m[1], $dato_m[2], $dato_m[3]);
			$time = $date->format("U");
			
			$deleted = $this->inbox->delete_older($time);
			if ($deleted > 0)
			{
				// melding
				ess::$b->page->add_message("Du slettet <b>" . $deleted . "</b> meldingstråd" . ($deleted == 1 ? '' : 'er') . ".");
			}
			
			else
			{
				// melding
				ess::$b->page->add_message("Ingen meldinger ble slettet.");
			}
			
			redirect::handle();
		}
	}
	
	protected function delete_older_form()
	{
		ess::$b->page->add_title("Slett alle meldinger");
		$date = ess::$b->date->get();
		
		// vis formen
		echo '
<h1>Meldinger</h1>
<div class="section" style="width: 300px; margin-left: auto; margin-right: auto">
	<h3>Slett alle meldinger</h3>
	<p>
		Her kan du slette alle meldingene du har. Alternativt kan du slette alle meldingene som du har mottatt før et visst klokkeslett.
	</p>
	<p>
		Slett alle meldinger som er mottatt <u>før</u>:
	</p>
	<form action="" method="post">
		<input type="hidden" name="slettalle" />
		<dl class="dl_20 dl_2x">
			<dt>Dato</dt>
			<dd><input type="text" name="dato" class="styled w100" value="' . postval("dato", $date->format("Y-m-d")) . '" /></dd>
			
			<dt>Tid</dt>
			<dd><input type="text" name="tid" class="styled w60" value="' . postval("tid", $date->format("H:i")) . '" /></dd>
		</dl>
		<h3 class="c">
			' . show_sbutton("Slett meldinger", 'name="confirm"') . '
			' . show_sbutton("Avbryt", 'name="abort"') . '
		</h3>
	</form>
</div>';
		
		$this->load_page();
	}
	
	/**
	 * Sett antall meldinger per side
	 */
	protected function set_per_page()
	{
		$ps = (int) $_POST['ps'];
		if ($ps > 0 && $ps <= 200)
		{
			login::data_set("innboks_per_side", $ps);
		}
		
		redirect::handle($this->addr(array("ps")));
	}
	
	/**
	 * Vis meldinger
	 */
	protected function show_messages()
	{
		// hent meldinger
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, login::data_get("innboks_per_side", 15));
		$meldinger = $this->inbox->get_messages($pagei, $this->show_deleted);
		
		$this->css();
		
		echo '
<div style="margin-top: 1em; font-size: 15px">
	<h1' . ($pagei->active > 1 && $show_deleted = false ? ' id="scroll_here"' : '') . ' style="float: left; margin-top: 0">Meldinger</h1>
	<p class="h_right" style="margin: 10px 0 0 0 !important">
		<a href="innboks_ny">Opprett ny melding</a>'.($pagei->total > 0 ? '
		<a href="'.htmlspecialchars($this->addr(null, null, "innboks_sok")).'">Søk</a>' : '').'
	</p>
</div>';
		
		// ingen meldinger å vise?
		if (!$meldinger)
		{
			echo '
<p class="clear">Du har ingen meldinger i din innboks eller utboks.</p>
<p>Så fort du sender eller mottar en melding vil den komme opp på denne siden.</p>';
		}
		
		// har vi noen meldinger å vise?
		else
		{
			$this->js();
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
<p class="c" style="margin: 25px auto 10px; width: 250px">'.$pagei->pagenumbers().'</p>';
			}
			
			echo '
<form action="" method="post">
	<table class="table clear" width="100%" id="meldinger">
		<thead>
			<tr>
				<th>Emne (<a href="#" class="box_handle_toggle" rel="it_id[]">Merk alle</a>)</th>
				<th><abbr title="Antall meldinger i meldingstråden">Ant.</abbr></th>
				<th><abbr title="Antall deltakere utenom deg selv">De.</abbr></th>
				<th>Siste</th>
				<th class="nowrap" colspan="1">Tid siste (<a id="skift_tid">veksle</a>)</th>
			</tr>
		</thead>
		<tbody>';
			
			$i = 0;
			$c = access::has("crewet");
			foreach ($meldinger as $row)
			{
				$o = $row['up_prev_other']
					? '<user id="'.$row['up_prev_other']['im_up_id'].'" /> <span class="im_utg">(utgående)</span>'
					: ($row['up_prev'] && !$row['up_prev'][0]
						? '<user id="'.$row['up_prev'][1].'" />'
						: (count($row['receivers']) > 1
							? '<user id="'.$row['receivers'][0]['ir_up_id'].'" /> <span class="im_utg">(utgående)</span>'
							: '<span class="dark">Ingen</span>'));
				
				// låst?
				// TODO: Skal ikke tråden se ut som den er låst når man er i crewet? Man har uansett mulighet til å svare når man går inn i tråden
				$locked = false;
				if (!$row['receivers_ok']) // ingen mottakere
				{
					$locked = true;
				}
				elseif ($row['ir_up_id'] != $this->inbox->u->player->id || (!$this->inbox->u->player->active && !$row['receivers_crew'])) // ikke samme spiller som sendte meldingen, evt. spilleren vår er deaktivert og mottakere er ikke crew
				{
					$locked = true;
				}
				
				echo '
			<tr class="box_handle' . ($row['ir_unread'] > 0 ? ' not_viewed' : (++$i % 2 == 0 ? ' color' : '')) . '">
				<td class="it_e">
					<input type="checkbox" name="it_id[]" value="' . $row['it_id'] . '" />
					<a href="innboks_les?id=' . $row['it_id'] . '" class="it_a">
						<span class="it_t_w">
							<span class="it_t">'.htmlspecialchars($row['it_title']).'</span>'.($row['ir_unread'] == 1
							? ' <span class="ny">(Ny!)</span>' : ($row['ir_unread'] > 1
							? ' <span class="ny">('.$row['ir_unread'].' nye!)</span>' : '')).($row['ir_deleted'] != 0
							? ' <span class="slettet">(Slettet)</span>' : '').($locked
							? ' <span class="it_locked">(Låst)</span>' : '').($row['ir_marked'] != 0
							? '<span class="ir_marked"> (Til oppfølging)</span>' : '').'
						</span>'.($row['id_text'] != "" ? '<br />
						<span class="id_text_w"><span class="id_text">'.$row['id_text'].'</span> <span class="id_up">('.($row['up_prev'][0] ? 'meg' : '<user id="'.$row['up_prev'][1].'" nolink />').')</span></span>' : '').'
					</a>
				</td>
				<td class="c">' . game::format_number($row['num_messages']) . '</td>
				<td class="c">'.(count($row['receivers'])-1).'</td>
				<td class="it_u">
					'.$o.'
					<div class="im_deltakere_det hide" rel="'.$row['it_id'].','.$row['ir_marked'].'">
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
				
				foreach($row['receivers'] as $r)
				{
					echo '
								<tr>
									<td><user id="'.$r['ir_up_id'].'" /></td>
									<td class="r">'.$r['num_messages'].'</td>
									<td class="r">'.($r['ir_unread'] > 0 ? '<b>'.$r['ir_unread'].'</b>' : $r['ir_unread']).'</td>'.(access::has("mod") ? '
									<td>'.$r['ir_views'].'</td>' : '').'
									<td>'.($r['up_access_level'] == 0 ? '<span class="dark">Død'.($c && $r['u_access_level'] != 0 && $r['u_active_up_id'] == $r['ir_up_id'] ? ', men bruker aktiv' : '').'</span>' : ($r['ir_deleted'] != 0 ? '<span class="dark">Slettet meldingen</span>' : 'Mottar nye meldinger')).'</td>
								</tr>';
				}
				
				echo '
							</tbody>
						</table>
					</div>
				</td>
				<td class="it_dato_w">
					<span class="it_dato_f it_dato_hide">'.ess::$b->date->get($row['ir_restrict_im_time'])->format(date::FORMAT_SEC).'</span>
					<span class="it_dato_f">' . game::timespan($row['ir_restrict_im_time'], game::TIME_ABS) . '</span>
				</td>
			</tr>';
			}
			
			echo '
		</tbody>
	</table>
	<p style="float: right; line-height: 25px" class="r red">
		' . show_sbutton("Slett merkede meldinger", 'name="slett" onclick="return confirm(\'Er du sikker på at du vil slette de merkede meldingene?\')"') . '<br />
		' . show_sbutton("Slett alle meldingene", 'name="slettalle"') . '
	</p>
</form>
<form action="" method="post">
	<p style="float: left">
		<select name="ps">';
			
			$list = array(10, 15, 20, 25, 30, 40, 50, 75, 100);
			if (!in_array($pagei->per_page, $list)) { $list[] = $pagei->per_page; sort($list); }
			foreach ($list as $a)
			{
				echo ' 
			<option value="'.$a.'"'.($a == $pagei->per_page ? ' selected="selected"' : '').'>Vis '.$a.' meldinger</option>';
			}
			
			echo '
		</select>
	</p>
</form>';
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
	<p class="c" style="margin: 13px auto 10px; width: 250px">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		$this->load_page();
	}
	
	/**
	 * CSS
	 */
	protected function css()
	{
		ess::$b->page->add_css('
.not_viewed td { /*background-color: #383838*/ }
#meldinger a { text-decoration: none }

.it_t_w { white-space: nowrap }
.it_a, .it_a:hover {
	text-decoration: none;
	display: block;
	width: 100%;
	height: 100%;
	margin: -3px -5px -4px 0;
	padding: 3px 5px 4px 0
}
.it_a:hover .it_t { color: #CCFF00; text-decoration: underline !important }
.id_text_w { white-space: nowrap }
.id_text { font-size: 9px; color: #777 }
.id_up { font-size: 9px; color: #777 }
.id_up img { display: none }
.im_utg { display: block; font-size: 9px; color: #AAA }

.ny { color: #FF0000; font-weight: bold }' . ($this->show_deleted ? '
.slettet { color: #BBBB99 }' : '').'
.it_locked { color: #BBBB99 }
.ir_marked { color: #BBBB99; font-weight: bold }

.it_u { text-align: center }
.it_dato_w { text-align: center; white-space: nowrap; color: #888888; font-size: 10px }
.it_last_up { text-align: center }
.ir_mark { margin: 8px 5px 5px }
.im_deltakere_det2, .it_dato_hide { display: none }');
	}
	
	/**
	 * JS
	 */
	protected function js()
	{
		ess::$b->page->add_js_domready('
	$("skift_tid").addEvent("click", function(e)
	{
		e.stop();
		$$(".it_dato_f").toggleClass("it_dato_hide");
	});
	$$("select[name=ps]").addEvent("change", function() { this.form.submit(); });
	
	$$(".it_u").each(function(elm)
	{
		var t = elm.getElement(".im_deltakere_det").set("tween", {duration: 150}).removeClass("hide");
		var b = new FBox();
		b.options.delay = 50;
		b.pos_x = "left"; b.pos_y = "top";
		b.rel_x = b.rel_y = elm;
		b.offset_x = ["center", 5]; b.offset_y = ["height", -2];
		b.connect(elm, true, false);
		b.create_box(t.dispose());
		b.autoclose();
		
		// marker for oppfølging
		var d = t.get("rel").split(","), w = elm.getParent().getElement(".it_t_w");
		if (d.length != 2) return;
		
		var cw = new Element("p", {
			"class": "ir_mark",
			"html": \'<input type="checkbox" id="im_mark_b_\'+d[0]+\'" /><label for="im_mark_b_\'+d[0]+\'"> Marker for oppfølging</label>\'
		}).inject(t, "top")
		var c = cw.getElement("input");
		var xhr;
		c.set("checked", d[1] != 0);
		
		var loader = new Element("span", { "text": " (lagrer..)" }).inject(cw).fade("hide");
		loader.start = function()
		{
			this.fade("show");
			this.set("text", " (lagrer..)");
		};
		loader.end = function(error)
		{
			this.set("text", error ? " (feilet..)" : " (OK)");
			this.fade("out");
		};
		
		c.addEvent("click", function()
		{
			if (xhr) xhr.cancel();
			else xhr = new Request({url: relative_path + "/ajax/inbox?it=" + d[0] + "&a=mark"}).addEvents({
				"success": function(text)
				{
					// stopp laster
					loader.end();
					
					// markert
					var m = w.getElement(".ir_marked");
					if (text == "MARK-TRUE")
					{
						c.set("checked", true);
						if (!m) new Element("span", { "class": "ir_marked", "text": " (Til oppfølging)" }).inject(w);
					}
					else if (text == "MARK-FALSE")
					{
						c.set("checked", false);
						if (m) m.destroy();
					}
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
	});');
	}
}








new page_innboks();