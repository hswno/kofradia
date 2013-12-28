<?php

class theme_helper
{
	public $theme;
	
	public function __construct($theme)
	{
		$this->theme = $theme;
	}
	
	public function get_footer()
	{
		$data = '
		<p id="first">';
		
		$pages = array();
		$pages[] = '<a href="&rpath;/node" class="help">Hjelp / innføring</a>';
		
		if (!login::$logged_in)
		{
			if (PHP_SELF != ess::$s['rpath'].'/index.php') $pages[] = '<a href="'.ess::$s['relative_path'].'/" class="login">Logg inn</a>';
			if (mb_strpos(PHP_SELF, "registrer") === false) $pages[] = '<a href="'.ess::$s['relative_path'].'/registrer" class="register">Registrer deg</a>';
			if (mb_strpos(PHP_SELF, "glemt_passord") === false) $pages[] = '<a href="'.ess::$s['relative_path'].'/glemt_passord" class="forgot">Glemt passord</a>';
		}
		else
		{
			$pages[] = '<a href="'.ess::$s['relative_path'].'/" class="frontpage">Forsiden</a>';
		}
		if (mb_strpos(PHP_SELF, "henvendelser") === false) $pages[] = '<a href="'.ess::$s['relative_path'].'/henvendelser" class="contact">Kontakt oss</a>';
		
		$data .= implode('
			', $pages) . '
		</p>
		
		<p id="last">
			<a href="https://hsw.no">Henrik Steen Webutvikling</a> - 
			<a href="http://kofradia.no/blogg/">Blogg</a> - 
			<a href="http://www.facebook.com/kofradia">Facebook</a> - 
			<a href="https://github.com/hswno/kofradia">GitHub</a>
		</p>';
		
		return $data;
	}
	
	public function get_box($title, $content, $id = null, $class = "")
	{
		$data = '
	<section class="guest_page_box'.($class ? ' '.$class : '').'"'.($id ? ' id="'.$id.'"' : '').'>
		<h1 class="guest_title">'.$title.'</h1>
		<div class="guest_content">'.$content.'
		</div>
	</section>';
		
		return $data;
	}
	
	public function draw_guest($content = null)
	{
		global $class_browser;
		
		if ($content === null)
		{
			$content = $this->get_box(ess::$b->page->generate_title(), ess::$b->page->content, $this->theme == "guest" ? "guest_page" : "guest_simple_page");
		}
		
		if ($this->theme == "guest_simple" || $this->theme == "logginn")
		{
			ess::$b->page->add_css('
html, body {
	min-width: 770px;
	max-width: 770px;
	font-size: 13px;
}');
		}
		
		$title = $this->theme == "logginn" ? "Kofradia | Kampen om broderskapet" : ess::$b->page->generate_title();
		
		// reklame fra AdSense
		$adds = $this->theme == "guest" || $this->theme == "node" ? '
	<aside id="theme_adds_r">
		<script type="text/javascript"><!--
		google_ad_client = "ca-pub-4574042726526883";
		/* guest */
		google_ad_slot = "5612632362";
		google_ad_width = 120;
		google_ad_height = 600;
		//-->
		</script>
		<script type="text/javascript" src="https://pagead2.googlesyndication.com/pagead/show_ads.js"></script>
	</aside>' : '';
		
		$data = '<!DOCTYPE html>
<html lang="no">
<head>
<title>'.$title.'</title>'.ess::$b->page->generate_head().'
</head>
<body class="theme_guest '.$class_browser.($adds ? ' theme_with_adds' : '').'">'.ess::$b->page->body_start;
		
		if ($this->theme == "node")
		{
			$data .= $this->get_header_node();
		}
		
		$data .= '
	<header>
		<h1><a href="'.ess::$s['rpath'].'/"><span>Kofradia.no - Kampen om broderskapet</span></a></h1>
		
		<aside id="facebook">'.($this->theme == "logginn" ? '
			<iframe src="https://www.facebook.com/plugins/like.php?app_id=245125612176286&amp;href=http%3A%2F%2Fwww.facebook.com%2Fkofradia&amp;send=false&amp;layout=button_count&amp;width=60&amp;show_faces=false&amp;action=like&amp;colorscheme=light&amp;font&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:100px; height:21px; margin: 10px 0 13px" allowTransparency="true"></iframe>' : '
			<p id="num">'.facebook::get_likes_num().'</p>').'
			<p id="sub"><a href="http://www.facebook.com/kofradia">Støtt oss på facebook</a></p>
		</aside>
	</header>';
		
		if ($this->theme == "guest")
		{
			$data .= '
	
	<nav class="guest_menu">'.kf_menu::build_menu().'
	</nav>';
		}
		
		$data .= '
	
	'.$adds.$content.'
	
	<footer>'.$this->get_footer().'
	</footer>
	
	<!--
	Script: '.round(microtime(true)-SCRIPT_START-ess::$b->db->time, 4).' sek
	Database: '.round(ess::$b->db->time, 4).' sek ('.ess::$b->db->queries.' spørring'.(ess::$b->db->queries == 1 ? '' : 'er').')
	-->'.ess::$b->page->body_end.'
</body>
</html>';
		
		return $data;
	}
	
	/**
	 * Hent header for node-templaten
	 */
	protected function get_header_node()
	{
		$data = '
	<aside class="node_header">
		<div class="node_header_r">';
		
		// logget inn?
		$extended = '';
		if (login::$logged_in)
		{
			$data .= '
			<p>Logget inn som '.game::profile_link().' | <a href="'.ess::$s['relative_path'].'/loggut?sid='.login::$info['ses_id'].'">Logg ut</a></p>';
			
			// utvidede tilganger?
			if (isset(login::$extended_access))
			{
				if (login::extended_access_is_authed())
				{
					$extended .= '
		<div id="node_crewm">
			<p class="first">
				<a href="'.ess::$s['relative_path'].'/crew/">Crew</a> (<a href="https://kofradia.no/crewstuff/" target="_blank">Stuff</a>)<br />
				<a href="https://github.com/hswno/kofradia/pulse" target="_blank">GitHub</a><br />
				<a href="'.ess::$s['relative_path'].'/crew/htpass">HT-pass</a>
			</p>';
					
					if (access::has("crewet")) $extended .= '
			<p>
				<a href="'.ess::$s['relative_path'].'/forum/forum?id=5">Crewforum</a> (<a href="'.ess::$s['relative_path'].'/forum/forum?id=6">arkiv</a>)<br />
				<a href="'.ess::$s['relative_path'].'/forum/forum?id=7">Idémyldringsforum</a><br />
			</p>';
					
					foreach (self::get_extended_access_boxes() as $box)
					{
						echo '
			<div class="link_box"><a href="'.$box[0].'">'.$box[1].'</a></div>';
					}
					
					$extended .= '
		</div>';
					
					$data .= '
			<p>Logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.ess::$s['relative_path'].'/extended_access?logout&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg ut</a></p>';
				}
				
				// ikke logget inn
				else
				{
					// har ikke passord?
					if (!isset(login::$extended_access['passkey']))
					{
						$data .= '
			<p><b>Ikke</b> logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.ess::$s['relative_path'].'/extended_access?create&amp;orign='.urlencode($_SERVER['REQUEST_URI']).'">Opprett passord</a></p>';
					}
					
					// logg inn lenke
					else
					{
						$data .= '
			<p><b>Ikke</b> logget inn som '.access::name(access::type(login::$user->player->data['up_access_level'])).' | <a href="'.ess::$s['relative_path'].'/extended_access?orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg inn</a></p>';
					}
				}
			}
		}
		
		else
		{
			$data .= '
		<p id="node_userinfo">Du er ikke logget inn | <a href="'.ess::$s['relative_path'].'/?orign='.urlencode($_SERVER['REQUEST_URI']).'">Logg inn</a> | <a href="'.ess::$s['relative_path'].'/registrer">Registrer</a></p>';
		}
		
		$data .= '
		</div>'.$extended.'
	</aside>';
		
		return $data;
	}
	
	/**
	 * Hent nyeste tråder og svar i forumet
	 */
	public function get_forum_new($limit = null)
	{
		$limit = (int) ($limit ?: 5);
		
		// hent forumdata
		$topics = ess::$b->db->query("
			SELECT ft_id, ft_title, ft_time, ft_up_id, ft_fse_id, fse_name
			FROM forum_topics
				LEFT JOIN forum_sections ON ft_fse_id = fse_id
			WHERE fse_id IN (1,2,3) AND ft_deleted = 0
			ORDER BY ft_time DESC
			LIMIT $limit");
		$replies = ess::$b->db->query("
			SELECT fr_id, fr_ft_id, fr_time, fr_up_id, ft_title, fse_name
			FROM forum_replies
				LEFT JOIN forum_topics ON fr_ft_id = ft_id AND ft_deleted = 0
				LEFT JOIN forum_sections ON ft_fse_id = fse_id
			WHERE fse_id IN (1,2,3) AND fr_deleted = 0
			ORDER BY fr_time DESC
			LIMIT $limit");
		
		$data = array();
		$times = array();
		while ($row = mysql_fetch_assoc($topics))
		{
			$data[] = array(
				'topic_id' => $row['ft_id'],
				'time' => $row['ft_time'],
				'user' => $row['ft_up_id'],
				'title' => $row['ft_title'],
				'section' => $row['fse_name'],
				'reply' => false
			);
			$times[] = $row['ft_time'];
		}
		while ($row = mysql_fetch_assoc($replies))
		{
			$data[] = array(
				'topic_id' => $row['fr_ft_id'],
				'reply_id' => $row['fr_id'],
				'time' => $row['fr_time'],
				'user' => $row['fr_up_id'],
				'title' => $row['ft_title'],
				'section' => $row['fse_name'],
				'reply' => true
			);
			$times[] = $row['fr_time'];
		}
		
		// sorter data
		array_multisort($times, SORT_DESC, SORT_NUMERIC, $data);
		
		return array_slice($data, 0, $limit);
	}
	
	/**
	 * Hent boks med forumsvar
	 */
	public function get_forum_box()
	{
		$data = '';
		foreach ($this->get_forum_new() as $item)
		{
			$data .= '
				<p><span class="time">'.ess::$b->date->get($item['time'])->format("H:i").':</span> <user id="'.$item['user'].'" /> '.($item['reply'] ? 'svarte i tråden' : 'opprettet').' <a href="'.ess::$s['rpath'].'/forum/topic?id='.$item['topic_id'].($item['reply'] ? '&amp;replyid='.$item['reply_id'] : '').'">'.htmlspecialchars($item['title']).'</a> i '.$item['section'].'</p>';
		}
		
		$data .= '
				<p class="last"><a href="forum">Gå til forumene &raquo;</a></p>';
		
		return $this->get_box("&raquo; Siste fra forumene", $data, null, "login_actions_box");
	}
	
	/**
	 * Hent boks med livefeed
	 */
	public function get_livefeed_box()
	{
		$lf = livefeed::get_latest(3);
		if (!$lf) return '';
		
		$data = '';
		foreach ($lf as $row)
		{
			$data .= '
			<p>'.ess::$b->date->get($row['lf_time'])->format("H:i").': '.$row['lf_html'].'</p>';
		}
		
		return $this->get_box("&raquo; Siste hendelser i spillet", $data, null, "login_actions_box");
	}
	
	/**
	 * Hent beste ranker siste 24 timer
	 */
	public function get_best_ranker_box()
	{
		$players = game::get_best_rankers();
		if (!$players) return '';
		
		$player = reset($players);
		
		$img = player::get_profile_image_static($player['up_profile_image_url']);
		$rank = game::rank_info($player['up_points'], $player['upr_rank_pos'], $player['up_access_level']);
		
		$data = '
		<p class="ranklist_box">
			<a href="'.ess::$s['relative_path'].'/p/'.rawurlencode($player['up_name']).'" title="Vis profil"><img src="'.htmlspecialchars($img).'" alt="Profilbilde" class="profile_image" /></a>
			<span class="ranklist_player">
				<span class="rp_up">'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</span><br />
				<span class="rp_rank">'.$rank['name'].'</span>
			</span>
			<span class="rp_familie">'.(empty($player['ff_links']) ? '<i class="rp_no_familie">Ingen broderskap</i>' : implode(", ", $player['ff_links'])).'</span>
			<span class="clear"></span>
		</p>';
		
		return $this->get_box("&raquo; Beste ranker siste 24 timer", $data, null, "login_ranker");
	}

	/**
	 * Hent diverse infobokser for crew
	 */
	public static function get_extended_access_boxes()
	{
		if (!isset(login::$extended_access)) return;
		if (!login::extended_access_is_authed()) return;
		
		$boxes = array();
		
		// support meldinger
		if (access::has("crewet"))
		{
			$row = tasks::get("support");
			if ($row['t_ant'] > 0)
			{
				$boxes[] = array(
					ess::$s['relative_path'].'/support/?a=panel&amp;kategori=oppsummering',
					'Det er <b>'.$row['t_ant'].'</b> '.fword("ubesvart supportmelding", "ubesvarte supportmeldinger", $row['t_ant']).'!');
			}
		}
		
		// hent antall nye rapporteringer fra cache
		$row = tasks::get("rapporteringer");
		if ($row['t_ant'] > 0)
		{
			$boxes[] = array(
				ess::$s['relative_path'].'/crew/rapportering',
				'Det er <b>'.$row['t_ant'].'</b> '.fword("ubehandlet rapportering", "ubehandlede rapporteringer", $row['t_ant']).'.');
		}
		
		// hent antall nye søknader fra cache
		$row = tasks::get("soknader");
		if ($row['t_ant'] > 0)
		{
			$boxes[] = array(
				ess::$s['relative_path'].'/crew/soknader',
				'Det er <b>'.$row['t_ant'].'</b> '.fword("ubehandlet søknad", "ubehandlede søknader", $row['t_ant']).'.');
		}
		
		// antall ubesvarte henvendelser
		if (access::has("mod"))
		{
			// hent antall nye henvendelser fra cache
			$row = tasks::get("henvendelser");
			
			if ($row['t_ant'] > 0)
			{
				$boxes[] = array(
					ess::$s['relative_path'].'/henvendelser?a',
					'Det er <b>'.$row['t_ant'].'</b> '.fword("ny henvendelse", "nye henvendelser", $row['t_ant']).' som er ubesvart.');
			}
		}
		
		// hendelser fra GitHub
		$github = \Kofradia\Users\GitHub::get(login::$user);
		if (!$github->hasActivated())
		{
			$boxes[] = array(
				ess::$s['relative_path'].'/github',
				'Du vil nå motta nye hendelser fra GitHub her. Trykk her for å se de siste hendelsene.');
		}
		else
		{
			$num_changes = $github->getCodeBehindCount() + $github->getOtherBehindCount();
			
			if ($num_changes > 0)
			{
				$boxes[] = array(
					ess::$s['relative_path'].'/github',
					'Det er <b>'.$num_changes.'</b> ny'.($num_changes == 1 ? '' : 'e').' hendelse'.($num_changes == 1 ? '' : 'r').' i GitHub.');
			}
		}
		
		return $boxes;
	}
}