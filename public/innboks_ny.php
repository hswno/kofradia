<?php

require "base.php";

class page_innboks_ny
{
	protected $receivers_limit;
	protected $receivers = array();
	
	protected $infos;
	protected $errors;
	
	public function __construct()
	{
		ess::$b->page->add_title("Meldinger", "Ny melding");
		
		// maks antall mottakere
		$this->receivers_limit = access::has("forum_mod") ? 9 : 4;
		
		// opprette melding?
		if (isset($_POST['message'])) $this->create();
		
		// mottaker fra en lenke?
		elseif (isset($_GET['mottaker']))
		{
			$result = ess::$b->db->query("
				SELECT up_id, up_name, up_access_level
				FROM users_players
				WHERE up_name = ".ess::$b->db->quote($_GET['mottaker'])."
				ORDER BY up_access_level = 0, up_last_online DESC
				LIMIT 1");
			while ($row = mysql_fetch_assoc($result))
			{
				$this->receivers[] = $row;
			}
		}
		
		// vis skjema
		$this->show();
	}
	
	protected function create()
	{
		// hent mottakere
		$this->parse_receivers();
		if ($this->receivers)
		{
			$this->create_handle();
		}
	}
	
	protected function create_handle()
	{
		// er det noen mottakere som ikke ble funnet?
		if (count($this->receivers) != count($this->players_list))
		{
			$this->report_missing();
		}
		
		// noen infomeldinger
		if (count($this->infos) > 0)
		{
			ess::$b->page->add_message(implode("<br />", $this->infos));
		}
		
		// noen feil?
		if (count($this->errors) > 0)
		{
			ess::$b->page->add_message(implode("<br />", $this->errors), "error");
			return;
		}
		
		// ingen mottakere?
		if (count($this->receivers) == 0)
		{
			ess::$b->page->add_message("Du må velge en eller flere mottakere.", "error");
			return;
		}
		
		// for mange mottakere?
		if (count($this->receivers) > $this->receivers_limit)
		{
			ess::$b->page->add_message("Du har valgt for mange mottakere. Du har en grense på <b>{$this->receivers_limit}</b> spillere.", "error");
			return;
		}
		
		// blokkert fra å sende meldinger? (kan kun sende til Crewet og med 1 mottaker)
		$blokkering = blokkeringer::check(blokkeringer::TYPE_MELDINGER);
		$blokkering_ok = true;
		if ($blokkering && count($this->receivers) == 1)
		{
			// kontroller at den ene mottakeren vi har valgt er i Crewet (tilgang til "crewet")
			$row = reset($this->receivers);
			$result = ess::$b->db->query("SELECT up_access_level FROM users_players WHERE up_id = {$row['up_id']}");
			$row = mysql_fetch_assoc($result);
			if (!$row || !in_array("crewet", access::types($row['up_access_level'])))
			{
				$blokkering_ok = false;
			}
		}
		
		// er mottakere crew?
		$receivers_crew = true;
		foreach ($this->receivers as $row)
		{
			if (!in_array("crewet", access::types($row['up_access_level'])))
			{
				$receivers_crew = false;
				break;
			}
		}
		
		// blokkert og for mange mottakere?
		if ($blokkering && count($this->receivers) > 1)
		{
			ess::$b->page->add_message("Du er blokkert fra å sende meldinger til andre enn Crewet. Du kan kun ha én mottaker. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			return;
		}
		
		// blokkert og mottaker er ikke i Crewet?
		if (!$blokkering_ok)
		{
			ess::$b->page->add_message("Du er blokkert fra å sende meldinger til andre enn Crewet. Blokkeringen varer til ".ess::$b->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), "error");
			return;
		}
		
		// er spilleren deaktivert, og mottakere er ikke crew?
		if (!login::$user->player->active && (!$receivers_crew || count($this->receivers) > 1))
		{
			ess::$b->page->add_message("Din spiller er deaktivert. Du har kun mulighet til å sende meldinger til Crewet. Kun én deltaker kan legges til.");
			return;
		}
		
		// ikke sende enda?
		if (!isset($_POST['post'])) return;
		
		// kontroller ventetid
		if (!$this->check_wait()) return;
		
		// behandle innhold
		$title = trim(postval("title"));
		$message = trim(postval("message"));
		
		// lengde
		$plain = strip_tags(game::bb_to_html($message));
		$plain = preg_replace("/[^a-zA-ZæøåÆØÅ0-9]/u", '', $plain);
		
		// er ikke begge feltene fylt ut?
		if (empty($title) || empty($message))
		{
			ess::$b->page->add_message("Både tittelfeltet og tekstfeltet må fylles ut.", "error");
			return;
		}
		
		// for kort tittel?
		if (mb_strlen($title) < 2)
		{
			ess::$b->page->add_message("Tittelfeltet må inneholde minst 2 tegn.", "error");
			return;
		}
		
		// for lang tittel?
		if (mb_strlen($title) > 35)
		{
			ess::$b->page->add_message("Tittelfeltet kan ikke være lengre enn 35 tegn.", "error");
			return;
		}
		
		// for kort melding?
		if (mb_strlen($plain) < 10)
		{
			ess::$b->page->add_message("Meldingen kan ikke inneholde færre enn 10 bokstaver/tall.", "error");
			return;
		}
		
		$it_id = login::$user->player->send_message($this->receivers, $title, $message);
		redirect::handle("innboks_les?id=$it_id");
	}
	
	/**
	 * Sjekk for ventetid
	 */
	protected function check_wait()
	{
		// ventetid - 20 sekunder
		// TODO: forbedre denne så den sjekker antall meldinger sendt siste 10 min eller liknende
		if (access::has("crewet")) $wait = 0;
		else $wait = max(0, login::$user->data['u_inbox_sent_time'] + 20 - time());
		
		// ventetid?
		if ($wait > 0)
		{
			ess::$b->page->add_message('Du må vente '.game::counter($wait).' før du kan sende en melding.', "error");
			putlog("LOG", "%c13%bMELDING FORSØK%b%c: %u".login::$user->player->data['up_name']."%u forsøkte å opprette en ny melding. Må vente {$wait} sekunder.");
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Finn ut hvilke spillere som vi ikke fant
	 */
	protected function report_missing()
	{
		$missing = array();
		
		$list = array();
		foreach ($this->receivers as $row)
		{
			$list[] = $row[$this->players_by_id ? 'up_id' : 'up_name'];
		}
		
		foreach ($this->players_list as $row)
		{
			if (!in_array($row, $list))
			{
				$missing[] = $row;
			}
		}
		
		$this->errors[] = "Følgende mottakere".($this->players_by_id ? ' med ID' : '').' finnes ikke: '.implode(", ", $missing);
	}
	
	protected $players_by_id;
	protected $players_list;
	protected function remove_player($row)
	{
		$match = $this->players_by_id ? $row['up_id'] : $row['up_name'];
		
		foreach ($this->players_list as $id => $val)
		{
			if ($val == $match)
			{
				unset($this->players_list[$id]);
				break;
			}
		}
	}
	
	/**
	 * Sett opp og kontroller mottakere
	 */
	protected function parse_receivers()
	{
		// sett opp søk
		$where = $this->get_receivers();
		if (!$where) return null;
		
		// hent brukere og evt. blokk
		$result = ess::$b->db->query("
			SELECT u_active_up_id, u_access_level, up_id, up_name, up_access_level, uc_id, uc_info
			FROM (
				SELECT u_active_up_id, u_access_level, up_id, up_name, up_access_level, uc_id, uc_info
				FROM users_players, users LEFT JOIN users_contacts ON u_id = uc_u_id AND uc_contact_up_id = ".login::$user->player->id." AND uc_type = 2
				WHERE up_u_id = u_id AND $where
				ORDER BY up_access_level = 0, up_last_online DESC
			) ref
			GROUP BY up_name");
		$this->errors = array();
		$this->infos = array();
		$receivers = array();
		while ($row = mysql_fetch_assoc($result))
		{
			// seg selv?
			if ($row['up_id'] == login::$user->player->id)
			{
				$this->remove_player($row);
				$this->errors[] = 'Du kan ikke legge til deg selv som mottaker. Du er mottaker av meldingen automatisk.';
			}
			
			// deaktivert?
			elseif ($row['up_access_level'] == 0 && (!access::has("crewet") || $row['u_access_level'] == 0 || count($this->players_list) > 1 || $row['u_active_up_id'] != $row['up_id']))
			{
				if (!access::has("crewet") || $row['u_access_level'] == 0 || $row['u_active_up_id'] != $row['up_id'])
				{
					$this->remove_player($row);
					$this->errors[] = '<user id="'.$row['up_id'].'" /> er død og kan ikke motta meldinger.';
				}
				else
				{
					$receivers[] = $row;
					$this->errors[] = '<user id="'.$row['up_id'].'" /> er død, men brukeren er aktivert. Kan motta meldinger hvis spilleren er den eneste mottakeren.';
				}
			}
			
			// blokkert?
			elseif ($row['uc_id'] && !access::has("crewet"))
			{
				$this->remove_player($row);
				
				// sett opp begrunnelse
				$info = $row['uc_info'];
				$reason = game::bb_to_html($info);
				$reason = empty($reason) ? '' : ' Begrunnelse: '.$reason;
				
				$this->errors[] = '<user id="'.$row['up_id'].'" /> har blokkert deg og kan ikke legges til som mottaker.'.$reason;
			}
			
			
			else
			{
				// forbeholdt mot å motta meldinger? -> men crew
				if (in_array($row['up_access_level'], ess::$g['access']['block_pm']) && access::has("crewet"))
				{
					$this->infos[] = '<user id="'.$row['up_id'].'" /> er egentlig reservert mot meldinger.';
				}
				
				// forbeholdt mot å motta meldinger? (ikke crew)
				elseif (in_array($row['up_access_level'], ess::$g['access']['block_pm']))
				{
					$result2 = ess::$b->db->query("
						SELECT uc_contact_up_id
						FROM users_players, users_contacts
						WHERE up_id = {$row['up_id']}
						  AND up_u_id = uc_u_id
						  AND uc_contact_up_id = ".login::$user->player->id."
						  AND uc_type = 1");
					$kontakt = mysql_num_rows($result2) > 0;
					
					// ikke kontakt? sjekk for mottatt melding innen 24 timer
					if (!$kontakt)
					{
						$expire = time() - 86400;
						$result2 = ess::$b->db->query("
							SELECT MAX(ir2.ir_restrict_im_time)
							FROM inbox_rel AS ir1, inbox_rel AS ir2
							WHERE ir1.ir_up_id = {$row['up_id']} AND ir1.ir_it_id = ir2.ir_it_id AND ir2.ir_up_id = ".login::$user->player->id." AND ir1.ir_deleted = 0");
						
						// for lenger enn 24 timer siden? --> kan ikke sende melding
						if (mysql_num_rows($result2) == 0 || mysql_result($result2, 0) < $expire)
						{
							$this->remove_player($row);
							
							$this->errors[] = '<user id="'.$row['up_id'].'" /> kan ikke legges til som mottaker fordi spilleren er reservert mot dette. For å kunne sende melding til denne spilleren må du være i kontaklisten til personen, eller ha mottatt en melding fra personen i løpet av de siste 24 timene.';
							putlog("NOTICE", "%bMELDING SPERRET%b: %u".login::$user->player->data['up_name']."%u forsøkte å sende melding til %u{$row['up_name']}%u men var ikke i kontaktlisten!");
							
							// lagre logg
							$file = GAMELOG_DIR."/message_reject_".date("Ymd_His").".log";
							$fh = @fopen($file, "w");
							if ($fh)
							{
								fwrite($fh, "Melding fra ".login::$user->player->data['up_name']." (ID: ".login::$user->player->id.")\n\n\n".print_r($_POST, true));
								fclose($fh);
								putlog("NOTICE", "%c4%bMESSAGE LOG SAVED TO %u$file%u");
							}
							else
							{
								putlog("NOTICE", "%c0,4%bERROR SAVING MESSAGE LOG TO FILE $file");
							}
							
							continue;
						}
					}
				}
				
				// kan sende melding
				$receivers[] = $row;
				
				// blokkert men vi er crew? (testet ovenfor hvis man ikke var crew)
				if ($row['uc_id'])
				{
					$info = $row['uc_info'];
					$reason = game::bb_to_html($info);
					$reason = empty($reason) ? '' : ' Begrunnelse: '.$reason;
					$this->infos[] = '<user id="'.$row['up_id'].'" /> har egentlig blokkert deg.'.$reason;
				}
			}
		}
		
		$this->receivers = $receivers;
	}
	
	/**
	 * Hent liste med mottakere
	 */
	protected function get_receivers()
	{
		// sjekk for mottakere
		$receivers = postval("receivers");
		
		// ID eller spillernavn? (javascript eller ikke)
		$where = false;
		$players_id = false;
		if (mb_substr($receivers, 0, 3) == "ID:")
		{
			$players_id = true;
			$players = array_unique(array_map("intval", explode(",", mb_substr($receivers, 3))));
			if (count($players) > 0 && count($players) <= 100)
			{
				$this->players_by_id = true;
				$this->players_list = $players;
				
				return "up_id IN (".implode(",", $players).")";
			}
		}
		
		// spillernavn
		else
		{
			$players = array_unique(explode(",", $receivers));
			if (count($players) > 0 && count($players) <= 100)
			{
				$this->players_by_id = false;
				$this->players_list = $players;
				
				return "up_name IN (".implode(",", array_map(array(ess::$b->db, "quote"), $players)).")";
			}
		}
		
		if (count($players) > 100)
		{
			ess::$b->page->add_message("For mange mottakere!", "error");
		}
		else
		{
			ess::$b->page->add_message("Ingen mottakere?!", "error");
		}
	}
	
	/**
	 * Vis siden for å sende melding
	 */
	protected function show()
	{
		// mottakere til uten JS
		$list = array();
		foreach ($this->receivers as $row)
		{
			$list[] = $row['up_name'];
		}
		$list = implode(",", $list);
		
		echo '
<div class="page_w0">
<h1>Ny melding</h1>
<p class="h_right" style="margin: -23px 0 0 0 !important"><a href="innboks">Tilbake til meldinger</a></p>
<form action="" method="post" onsubmit="return innboks_ny.submit()" id="rec_form">
	<div class="section">
		<h3>Innhold</h3>
		<dl class="dd_auto_100">
			<dt>Mottakere <span id="rec_s"></span></dt>
			<dd>
				<input type="text" name="receivers" value="'.htmlspecialchars(postval("receivers", $list)).'" class="styled w300" id="rec" />
				<noscript>Separer med komma (,).</noscript>
				<div id="rec_new" class="section">
					<h3>Legg til mottaker</h3>
					<dl class="dl_20 dl_2x">
						<dt>Navn</dt>
						<dd><input type="text" class="styled w100" /></dd>
					</dl>
					<ul></ul>
					<div id="rec_newm"></div>
				</div>
				<ul id="rec_list"></ul>
				<div style="clear: both"></div>
			</dd>
			
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title")).'" class="styled w300" maxlength="35" /></dd>
			
			<dt>Tekst</dt>
			<dd><textarea name="message" rows="20" cols="75" id="melding">'.htmlspecialchars(postval("message")).'</textarea></dd>
			
			<dt'.(isset($_POST['preview']) && isset($_POST['message']) ? '' : ' style="display: none"').' id="pdt">Forhåndsvisning</dt>
			<dd'.(isset($_POST['preview']) && isset($_POST['message']) ? '' : ' style="display: none"').' id="pdd">'.(!isset($_POST['message']) || empty($_POST['message']) ? 'Tomt?!' : game::bb_to_html($_POST['message'])).'</dd>
			<div class="clear"></div>
		</dl>
		<h3 class="c">
			'.show_sbutton("Send melding", 'name="post" accesskey="s"').'
			'.show_sbutton("Forhåndsvis", 'name="preview" accesskey="p" onclick="previewDL(event, \'melding\', \'pdt\', \'pdd\')"').'
		</h3>
	</div>
</form>
</div>';
		
		$this->css();
		$this->js();
		
		ess::$b->page->load();
	}
	
	protected function css()
	{
		// css
		ess::$b->page->add_css('
#rec_list {
	float: left;
	padding: 3px;
	margin: 0 0 3px 10px;
	list-style: none;
	border: 2px solid #292929;
	background-color: #1C1C1C;
	width: 160px;
	text-align: right;
	display: none;
}
/*#rec_list img { display: none }*/
#rec_list li { padding: 2px }
#rec_list li.hover { background-color: #222222 }

.r_user { float: left }
.r_del { padding: 1px 4px; color: #FF0000; text-decoration: none; font-size: 14px; font-weight: bold }
.r_del:hover { color: #FF0000; text-decoration: underline }


#rec_new {
	display: none;
	float: left;
	margin: 0 0 3px 0;
	width: 150px;
}
#rec_new ul {
	margin: 10px 0;
	padding: 0;
	list-style: none;
	text-align: right;
	display: none;
}
/*#rec_new li img { display: none }*/
#rec_new li { padding: 2px }
#rec_new li.hover { background-color: #222222 }
#rec_new ul .add { padding: 1px 4px; color: #CCCCCC; text-decoration: none; font-size: 16px; font-weight: bold }
#rec_new ul a.add { color: #88CC00 }
#rec_new ul a.add:hover { text-decoration: underline }

#rec_newm { display: none; margin: 10px 0; padding: 0; color: #88CC00 }');
	}
	
	protected function js()
	{
		// mottakere
		$list = array();
		foreach ($this->receivers as $row)
		{
			$list[] = array($row['up_id'], $row['up_name'], game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']));
		}
		
		// hent javascript filen til innboksen
		ess::$b->page->add_js_file(ess::$s['relative_path']."/js/innboks.js");
		
		// javascript
		ess::$b->page->add_js_domready('
	innboks_ny.receivers = '.js_encode($list).';
	innboks_ny.limit = '.$this->receivers_limit.';
	innboks_ny.init();');
	}
}

new page_innboks_ny();