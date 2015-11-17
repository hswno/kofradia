<?php

class page_forsiden extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		// sende til side?
		if (isset($_GET['orign'])) redirect::handle($_GET['orign'], redirect::SERVER, login::$info['ses_secure']);
		
		parent::__construct($up);
		
		access::no_guest();
		ess::$b->page->add_title("Hovedsiden");
		
		$this->show();
		ess::$b->page->load();
	}
	
	protected function show()
	{
		$this->player_dead();

		// vis julekalender kun i desember 2015
		if (ess::$b->date->get()->format("Y-m") == "2015-12") {
			new page_julekalender($this->up);
		}
		
		echo '
<div class="page_w4" style="margin-top: 0">
<div class="col2_w">
	<div class="col_w left">
		<div class="col" style="margin-right: 15px">';
		
		$this->show_auksjoner();
		$this->show_aviser();
		$this->show_beste_rankere();
		$this->show_random_player();
		
		echo '
		</div>
	</div>
	<div class="col_w right">
		<div class="col" style="margin-left: 15px">';
		
		$this->show_facebook();
		$this->show_livefeed();
		
		echo '
		</div>
	</div>
</div>
</div>';
		
		$this->login_status();
	}
	
	/**
	 * Hent Facebook-cache
	 */
	protected function get_facebook_cache()
	{
		// har vi cache?
		$data = cache::fetch("facebook_posts");
		if ($data) return $data;
		
		// authentiser
		$app_id = KOF_FB_APP_ID;
		$app_secret = KOF_FB_APP_SECRET;

		if (!$app_id || !$app_secret) return null;
		
		$ret = @file_get_contents("https://graph.facebook.com/oauth/access_token?client_id=$app_id&client_secret=$app_secret&grant_type=client_credentials");
		if ($ret === false)
		{
			// kunne ikke hente data
			putlog("CREWCHAN", "Henting av Facebook-data feilet.");
			cache::store("facebook_posts", array());
		}
		
		$info = null;
		parse_str($ret, $info);
		
		// hent JSON
		$json = @file_get_contents("https://graph.facebook.com/kofradia/posts?access_token={$info['access_token']}");
		$data = json_decode($json, true);
		cache::store("facebook_posts", $data);
		
		return $data;
	}
	
	/**
	 * Hent data for Facebook feed
	 */
	protected function get_facebook_feed()
	{
		$data = $this->get_facebook_cache();
		if (!$data) return array();
		
		$links = array();
		foreach ($data['data'] as $row)
		{
			if ($row['from']['id'] != "142869472410919") continue;
			
			$link = array(
				"text" => isset($row['message']) ? $row['message'] : (isset($row['name']) ? $row['name'] : "ukjent tekst"),
				"link" => isset($row['link']) ? $row['link'] : null,
				"time" => strtotime($row['created_time'])
			);
			
			// TODO: det kommer mange "ukjent tekst" i feeden. returnert json fra facebook bør sjekkes igjen. midlertidig fiks å skjule disse
			if ($link['text'] !== "ukjent tekst") $links[] = $link;
		}
		
		return $links;
	}
	
	/**
	 * Vis siste avisutgivelser
	 */
	protected function show_aviser()
	{
		// hent siste avisutgivelsene
		$result = \Kofradia\DB::get()->query("
			SELECT
				ff_id, ff_name,
				ffn_id, ffn_published_time, ffn_title
			FROM ff_newspapers
				JOIN ff ON ffn_ff_id = ff_id
			WHERE ffn_published != 0
			ORDER BY ffn_published_time DESC
			LIMIT 8");
		
		// ingen utgivelser?
		if ($result->rowCount() == 0) return;
		
		ess::$b->page->add_css('
.aviser p {
	color: #CCC;
}
.aviser ul { padding: 0 }
.aviser li {
	margin-top: 3px;
	list-style: none;
}
.aviser .avis { text-decoration: none; color: #AAA }
.aviser .avis:hover { color: #DDD }
.aviser .avis_ff { color: #888; font-size: 10px }
.aviser .avis_ff a { color: #555; text-decoration: none }
.aviser .avis_ff a:hover { color: #888 }
.aviser .avis_time { color: #555; font-size: 10px; float: right }');
		
		// vis oversikt
		$data = '
	<p>Siste utgitte aviser</p>
	<ul>';
		
		while ($row = $result->fetch())
		{
			$date = ess::$b->date->get($row['ffn_published_time']);
			
			$data .= '
		<li><span class="avis_time">'.$date->format("j. ").mb_substr($date->format(date::FORMAT_MONTH), 0, 3).'</span> <a class="avis" href="'.ess::$s['relative_path'].'/ff/avis?ff_id='.$row['ff_id'].'&amp;ffn='.$row['ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a><br />
			<span class="avis_ff">av <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></li>';
		}
		
		$data .= '
	</ul>';
		
		$this->put_box($data, "aviser");
	}
	
	/**
	 * Vis siste avisutgivelser
	 */
	protected function show_aviser2()
	{
		// hent siste avisutgivelsene
		$result = \Kofradia\DB::get()->query("
			SELECT
				ff_id, ff_name,
				ffn_id, ffn_published_time, ffn_title
			FROM ff_newspapers
				JOIN ff ON ffn_ff_id = ff_id
			WHERE ffn_published != 0
			ORDER BY ffn_published_time DESC
			LIMIT 8");
		
		// ingen utgivelser?
		if ($result->rowCount() == 0) return;
		
		ess::$b->page->add_css('
.aviser p {
	color: #CCC;
}
.aviser ul { padding: 0 }
.aviser li {
	list-style: none;
}
.aviser .avis { text-decoration: none; color: #AAA }
.aviser .avis:hover { color: #DDD }
.aviser .avis_ff { color: #888; font-size: 10px }
.aviser .avis_ff a { color: #555; text-decoration: none }
.aviser .avis_ff a:hover { color: #888 }
.aviser .avis_time {
	color: #555; font-size: 10px;
}');
		
		// vis oversikt
		$data = '
	<p>Siste utgitte aviser</p>
	<ul>';
		
		while ($row = $result->fetch())
		{
			$date = ess::$b->date->get($row['ffn_published_time']);
			
			$data .= '
		<li><a class="avis" href="'.ess::$s['relative_path'].'/ff/avis?ff_id='.$row['ff_id'].'&amp;ffn='.$row['ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a><br />
			<span class="avis_time">'.$date->format("j. ").mb_substr($date->format(date::FORMAT_MONTH), 0, 3).'</span> <span class="avis_ff">av <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></li>';
		}
		
		$data .= '
	</ul>';
		
		$this->put_box($data, "aviser");
	}
	
	/**
	 * Vis Facebook feed
	 */
	protected function show_facebook()
	{
		$feed = $this->get_facebook_feed();
		if (!$feed || count($feed) == 0) return;
		
		ess::$b->page->add_css('
.feed_data {
	padding: 3px 0 0;
}
.feed_data ul {
	margin: 8px 5px;
}
.feed_data li {
	color: #999999;
}
.blog_time {
	color: #DDD;
}');
		
		$data = '
	<p class="c"><a href="http://www.facebook.com/kofradia"><img src="'.STATIC_LINK.'/themes/sm/facebook.png" alt="Facebook" /></a></p>
	<ul>';
		
		$i = 0;
		foreach ($feed as $row)
		{
			if ($i++ == 9) break;
			
			$text = htmlspecialchars(str_replace(" | Kofradia.no Blogg", "", $row['text']));
			if ($row['link']) $text = '<a href="'.htmlspecialchars($row['link']).'">'.$text.'</a>';
			
			$date = ess::$b->date->get($row['time']);
			
			$data .= '
		<li><span class="blog_time">'.$date->format("j. ").mb_substr($date->format(date::FORMAT_MONTH), 0, 3).': </span>'.$text.'</li>';
		}
		
		$data .= '
	</ul>
	<div style="text-align: center"><iframe src="https://www.facebook.com/plugins/like.php?app_id=245125612176286&amp;href=http%3A%2F%2Fwww.facebook.com%2Fkofradia&amp;send=false&amp;layout=button_count&amp;width=50&amp;show_faces=false&amp;action=like&amp;colorscheme=light&amp;font&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:100px; height:21px; margin: 10px auto" allowTransparency="true"></iframe></div>';
		
		$this->put_box($data, "feed_data");
	}
	
	/**
	 * Vis live-feed
	 */
	protected function show_livefeed()
	{
		// hent siste oppføringene
		$result = livefeed::get_latest();
		if (count($result) == 0) return;
		
		ess::$b->page->add_css('
.livefeed {
	/*max-height: 200px;
	overflow: auto;
	margin: 10px 0;*/
}
.livefeed ul { padding: 0 }
.livefeed li { list-style: none; padding-top: 5px }
.livefeed .time { color: #888 }');
		
		$data = '
	<div class="livefeed">
		<ul>';
		
		foreach ($result as $row)
		{
			$data .= '
			<li><span class="time">'.ess::$b->date->get($row['lf_time'])->format("H:i").':</span> <span class="feedtext">'.$row['lf_html'].'</span></li>';
		}
		
		$data .= '
		</ul>
	</div>';
		
		$this->put_box($data);
	}
	
	/**
	 * Vis en tilfeldig spiller
	 */
	protected function show_random_player()
	{
		$expire = time() - 86400;
		
		// hent en tilfeldig spiller
		$result = \Kofradia\DB::get()->query("
			SELECT up_id
			FROM users_players
			WHERE up_access_level != 0 AND up_access_level < ".ess::$g['access_noplay']." AND up_last_online > $expire AND up_id != ".$this->up->id."
			ORDER BY RAND()
			LIMIT 1");
		
		$row = $result->fetch();
		if (!$row) return;
		
		$up = player::get($row['up_id']);
		
		// hent FF
		$result = \Kofradia\DB::get()->query("
			SELECT ffm_priority, ff_id, ff_name, ff_type
			FROM ff_members JOIN ff ON ffm_ff_id = ff_id
			WHERE ffm_up_id = $up->id AND ffm_status = 1 AND ff_inactive = 0 AND ff_is_crew = 0
			ORDER BY ff_name");
		$ff = array();
		while ($row = $result->fetch())
		{
			$type = ff::$types[$row['ff_type']];
			$row['posisjon'] = ucfirst($type['priority'][$row['ffm_priority']]);
			$ff[] = $row;
		}
		
		ess::$b->page->add_css('
.tilfeldig_spiller_img { float: right; margin: 8px 0 8px 5px; max-height: 80px; overflow: hidden }
.tilfeldig_spiller_img img { width: 60px; display: block }');
		
		$data = '';
		
		$data .= '
	<p class="tilfeldig_spiller_img profile_image"><a href="'.$up->generate_profile_url(false).'"><img src="'.htmlspecialchars($up->get_profile_image()).'" alt="Profilbilde" /></a></p>
	<p class="c">Tilfeldig spiller: '.$up->profile_link().'</p>
	<ul>
		<li>Registrert '.ess::$b->date->get($up->data['up_created_time'])->format(date::FORMAT_NOTIME).'</li>
		<li>'.$up->rank['name'].', plassert som nr. '.$up->data['upr_rank_pos'].'</li>';
		
		foreach ($ff as $row)
		{
			$data .= '
		<li>'.$row['posisjon'].' i <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></li>';
		}
		
		$data .= '
	</ul>';
		
		$this->put_box($data);
	}
	
	/**
	 * Vis beste rankere forrige periode
	 */
	protected function show_beste_rankere()
	{
		$d = ess::$b->date->get();
		$a = $d->format("H") < 21 ? 2 : 1;
		$d->modify("-$a day");
		$d->setTime(21, 0, 0);
		$date_from = $d->format("U");
		
		$d->modify("+1 day");
		$date_to = $d->format("U");
		
		// hent statistikk
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, sum_uhi_points, up_points, up_last_online, up_profile_image_url, upr_rank_pos
			FROM
				(
					SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
					FROM users_hits
						JOIN users_players ON up_id = uhi_up_id AND (up_access_level != 0 OR up_deactivated_time < $date_to) AND up_access_level < ".ess::$g['access_noplay']."
					WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
					GROUP BY uhi_up_id
					HAVING sum_uhi_points > 0
					ORDER BY sum_uhi_points DESC
					LIMIT 3
				) ref,
				users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE uhi_up_id = up_id");
		
		if ($result->rowCount() == 0) return;
		
		$players = array();
		$up_list = array();
		while ($row = $result->fetch())
		{
			$players[] = $row;
			$up_list[] = $row['up_id'];
		}
		
		// hent familier hvor spilleren er medlem
		$result_ff = \Kofradia\DB::get()->query("
			SELECT ffm_up_id, ffm_priority, ff_id, ff_type, ff_name
			FROM
				ff_members
				JOIN ff ON ff_id = ffm_ff_id AND ff_type = 1 AND ff_inactive = 0
			WHERE ffm_up_id IN (".implode(",", $up_list).") AND ffm_status = ".ff_member::STATUS_MEMBER."
			ORDER BY ff_name");
		$familier = array();
		while ($row = $result_ff->fetch())
		{
			$pos = ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']];
			$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ff_name']).'</a>';
			$familier[$row['ffm_up_id']][] = $text;
		}
		
		$data = '
	<p>Beste rankere siste periode</p>';
		
		$e = 0;
		foreach ($players as $row)
		{
			$e++;
			$img = player::get_profile_image_static($row['up_profile_image_url']);
			$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
			
			$data .= '
		<p class="ranklist_box">
			<a href="'.ess::$s['relative_path'].'/p/'.rawurlencode($row['up_name']).'" title="Vis profil"><img src="'.htmlspecialchars($img).'" alt="Profilbilde" class="profile_image" /></a>
			<span class="ranklist_pos">#'.$e.'</span>
			<span class="ranklist_player">
				<span class="rp_up">'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</span><br />
				<span class="rp_rank">'.$rank['name'].'</span>
			</span>
			<span class="rp_familie">'.(!isset($familier[$row['up_id']]) ? '<i class="rp_no_familie">Ingen broderskap</i>' : implode(", ", $familier[$row['up_id']])).'</span>
		</p>';
			
			if ($e == 15) break;
		}
		
		$data .= '
	<p class="dark">En rankperiode er fra sist klokka var 21:00 og 24 timer før. De beste rankerene <a href="'.ess::$s['rpath'].'/node/59">mottar bonus</a> for sin innsats.</p>';
		
		$this->put_box($data);
	}
	
	/**
	 * Vis aktive auksjoner
	 */
	protected function show_auksjoner()
	{
		// hent aktive auksjonene og høyeste bud
		$time = time();
		$result = \Kofradia\DB::get()->query("
			SELECT
				a_id, a_type, a_title, a_up_id, a_end, a_bid_start, a_num_bids,
				ab_bid, ab_up_id, ab_time
			FROM auksjoner
				LEFT JOIN (
					SELECT ab_id, ab_a_id, ab_bid, ab_up_id, ab_time
					FROM auksjoner_bud
					WHERE ab_active != 0
					ORDER BY ab_time DESC
				) AS auksjoner_bud ON a_id = ab_a_id
			WHERE a_active != 0 AND a_completed = 0 AND a_start <= $time AND a_end >= $time
			GROUP BY a_id
			ORDER BY a_end, ab_time DESC, a_title
			LIMIT 5");
		
		// ingen aktive auksjoner?
		if ($result->rowCount() == 0) return;
		
		$data = '
	<p>Aktive auksjoner</p>
	<dl class="dd_right">';
		
		while ($row = $result->fetch())
		{
			$type = auksjon_type::get($row['a_type']);
			
			$data .= '
		<dt><a href="'.ess::$s['relative_path'].'/auksjoner?a_id='.$row['a_id'].'">'.$type->format_title($row).'</a>'.($type->have_up && $row['a_up_id'] ? ' av <user id="'.$row['a_up_id'].'" />' : '').'</dt>
		<dd>'.ess::$b->date->get($row['a_end'])->format(date::FORMAT_SEC).'<br />'.(!$row['ab_time'] ? '
			Budstart: '.game::format_cash($row['a_bid_start']) : '
			'.game::format_cash($row['ab_bid']).' av <user id="'.$row['ab_up_id'].'" />').'
			</dd>';
		}
		
		$data .= '
	</dl>';
		
		$this->put_box($data);
	}
	
	/**
	 * Vis boks
	 */
	protected function put_box($data, $class = null)
	{
		static $got_css = false;
		if (!$got_css)
		{
			ess::$b->page->add_css('
.forside_box {
	background-color: #202020;
	overflow: hidden;
	padding: 0 1em;
	margin-top: 30px;
}
.forside_box li { margin-top: 3px }');
			$got_css = true;
		}
		
		echo '
<div class="forside_box r4'.($class ? ' '.$class : '').'">'.$data.'
</div>';
	}
	
	/**
	 * Sjekk om spilleren er død
	 */
	protected function player_dead()
	{
		// ikke død?
		if (login::$user->player->active) return;
		
		$killed = login::$user->player->data['up_deactivated_dead'];
		$deact_self = false;
		
		// deaktivert self?
		if (!$killed)
		{
			// deaktivert av seg selv?
			if (!empty(login::$user->player->data['up_deactivated_up_id']))
			{
				$deact_self = login::$user->player->data['up_deactivated_up_id'] == login::$user->player->id;
				if (!$deact_self)
				{
					$result = \Kofradia\DB::get()->query("SELECT u_id FROM users JOIN users_players ON u_id = up_u_id WHERE up_id = ".login::$user->player->data['up_deactivated_up_id']);
					$row = $result->fetch();
					unset($result);
					if ($row && $row['u_id'] == login::$user->id) $deact_self = true;
				}
			}
		}
		
		ess::$b->page->add_css('
.player_dead {
	background-color: #222222;
	margin: 30px auto;
	padding: 0 10px;
	width: 300px;
	overflow: hidden;
	border-top: 2px solid #333333;
	border-bottom: 2px solid #333333;
}
');
		
		echo '
<div class="player_dead r2">
	<h1>'.($killed == 2 ? 'Du blødde ihjel' : ($killed ? 'Du ble drept' : 'Du er deaktivert')).'</h1>
	<p>'.($deact_self ? 'Du deaktivert din spiller' : 'Din spiller '.($killed == 2 ? 'blødde ihjel på grunn av lite energi og helse' : ($killed ? 'ble drept' : 'ble deaktivert'))).' '.ess::$b->date->get(login::$user->player->data['up_deactivated_time'])->format().'.'.($killed == 1 ? ' Du vil ikke kunne se hvem som drepte deg uten å få en spiller som vitnet angrepet til å fortelle deg det.' : '').'</p>'.(!$killed && !$deact_self ? '
	<p>Begrunnelse for deaktivering: '.(empty(login::$user->player->data['up_deactivated_reason']) ? 'Ingen begrunnelse oppgitt.' : game::bb_to_html(login::$user->player->data['up_deactivated_reason'])).'</p>' : '').'
	<p>Ved å gå inn på "min side" og "min bruker" kan du se informasjon om dine tidligere spillere. Du kan trykke på spillernavnet som står oppført for å komme til "min spiller" som gjelder for den spilleren.</p>
	<p>Du må opprette en <a href="lock?f=player">ny spiller</a> for å kunne fortsette i spillet med en ny spiller.</p>
</div>';
	}
	
	/**
	 * Vis logg inn-status
	 */
	protected function login_status()
	{
		// logg inn status!
		if (login::$info['ses_expire_type'] == LOGIN_TYPE_ALWAYS)
		{
			// alltid logget inn
			echo '<p class="login_type">Du er alltid logget inn!</p>';
		}
		elseif (login::$info['ses_expire_type'] == LOGIN_TYPE_BROWSER)
		{
			// logget inn til brukeren lukker nettleseren
			echo '<p class="login_type">Du er logget inn til du lukker nettleseren!</p>';
		}
		else
		{
			// til timeout
			echo '<p class="login_type">Du er logget inn til det har gått 15 minutter uten aktivitet. Det vil si hvis du ikke gjør noe før: '.ess::$b->date->get(login::$info['ses_expire_time'])->format().'.</p>';
		}
	}
}
