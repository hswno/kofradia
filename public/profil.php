<?php

define("ALLOW_GUEST", true);

require "base.php";
global $_game, $_lang, $__server;

ess::$b->page->add_title("Profil");
$by_id = false;
$player = false;

// tilfeldig profil?
if (isset($_GET['random']))
{
	$last = time() - 604800; // 1 uke
	
	// med musikk?
	$where = "";
	if (isset($_GET['music']))
	{
		$where .= " AND up_profile_text LIKE '%[music]%[/music]%'";
	}
	
	$result = ess::$b->db->query("SELECT up_name FROM users_players WHERE up_access_level != 0 AND up_last_online > $last$where ORDER BY RAND() LIMIT 1");
	$name = mysql_result($result, 0);
	
	redirect::handle("/p/".rawurlencode($name), redirect::ROOT);
}

// via spillernavn eller spillernavn og id
// ?name=
// ?name=&id=
if (isset($_GET['name']))
{
	$name = $_GET['name'];
	
	// søke med ID også?
	if (isset($_GET['id']))
	{
		$up_id = (int) $_GET['id'];
		$result = ess::$b->db->query("SELECT up_id, up_name FROM users_players WHERE up_id = $up_id AND up_name = ".ess::$b->db->quote($name));
		$by_id = true;
	}
	
	else
	{
		$result = ess::$b->db->query("SELECT up_id, up_name FROM users_players WHERE up_name = ".ess::$b->db->quote($name)." ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1");
	}
	
	// fant ingen spller?
	$row = mysql_fetch_assoc($result);
	if (!$row)
	{
		ess::$b->page->add_message("Fant ikke spilleren.", "error");
		redirect::handle("finn_spiller?finn=".urlencode($name), redirect::ROOT);
	}
	
	// sende til korrekt side?
	if (!isset($_SERVER['REDIRECT_URL']))
	{
		// send til korrekt side
		$address = game::address("/p/".rawurlencode($row['up_name']).($by_id ? "/{$row['up_id']}" : ""), $_GET, array("id", "name"));
		redirect::handle($address, redirect::ROOT);
	}
	
	if (login::$logged_in && $row['up_id'] == login::$user->player->id) $player = login::$user->player;
	else $player = player::get($row['up_id']);
}

// kun via id?
// ?id=
elseif (isset($_GET['id']))
{
	$up_id = (int) $_GET['id'];
	$result = ess::$b->db->query("SELECT up_id, up_name FROM users_players WHERE up_id = $up_id");
	$row = mysql_fetch_assoc($result);
	
	if (!$row)
	{
		ess::$b->page->add_message("Fant ikke spilleren.", "error");
		redirect::handle("finn_spiller", redirect::ROOT);
	}
	
	// send til korrekt side
	$address = game::address("/p/".rawurlencode($row['up_name'])."/{$row['up_id']}", $_GET, array("id"));
	redirect::handle($address, redirect::ROOT);
}

// gammelt format?
// ?user=
elseif (isset($_GET['user']))
{
	// send til korrekt side
	$address = game::address("/p/".rawurlencode($_GET['user']), $_GET, array("user"));
	redirect::handle($address, redirect::ROOT);
}

// mangler info
else
{
	ess::$b->page->add_message("Manglet brukeridentifikasjon.", "error");
	redirect::handle("finn_spiller", redirect::ROOT);
}

ess::$b->page->add_title($player->data['up_name']);

// moderasjon
if (access::has("crewet", NULL, NULL, true))
{
	// javascript
	ess::$b->page->add_js_domready('
	new KeySequence("esc,M,esc", function()
	{
		navigateTo(relative_path+"/min_side?up_id='.$player->id.'&a=crew");
	});');
}

// loggfør visning
if (!login::$logged_in)
{
	putlog("PROFILVIS", "%c6%bVIS-PROFIL:%b%c Ikke-innlogget-person viste profilen til %u{$player->data['up_name']}%u (up_id: ".$player->id.")");
}
elseif (login::$user->player->id != $player->id)
{
	putlog("PROFILVIS", "%c6%bVIS-PROFIL:%b%c %u".login::$user->player->data['up_name']."%u viste profilen til %u{$player->data['up_name']}%u (up_id: ".$player->id.")");
}


// legg til som besøkende til denne profilen
if (!login::$logged_in || (!access::is_nostat() && login::$user->id != $player->data['up_u_id']))
{
	// anonym
	if (!login::$logged_in)
	{
		$siste = $player->data['up_profile_anon_time'];
		$player->data['up_profile_anon_time'] = time();
		ess::$b->db->query("UPDATE users_players SET up_profile_anon_time = {$player->data['up_profile_anon_time']} WHERE up_id = {$player->id}");
	}
	
	// innlogget
	elseif (!access::is_nostat() && login::$user->id != $player->data['up_u_id'])
	{
		// når besøkte vi profilen sist?
		$siste = 0;
		$result = ess::$b->db->query("SELECT time FROM users_views WHERE uv_up_id = $player->id AND uv_visitor_up_id = ".login::$user->player->id);
		if (mysql_num_rows($result))
		{
			$siste = mysql_result($result, 0);
		}
		
		ess::$b->db->query("
			INSERT INTO users_views SET uv_up_id = $player->id, uv_visitor_up_id = ".login::$user->player->id.", time = ".time()."
			ON DUPLICATE KEY UPDATE time = ".time());
	}
	
	// oppdater antall visninger -- kun hvis det er en annen bruker som viser profilen
	// oppdater kun hvis det har gått mer enn 90 sekunder siden forrige visning (30 sekunder for anonyme)
	if ($siste + (login::$logged_in ? 90 : 30) < time())
	{
		ess::$b->db->query("UPDATE users_players SET up_profile_hits = up_profile_hits + 1 WHERE up_id = $player->id");
		$player->data['up_profile_hits']++;
	}
}

// hent siste besøkende
$expire = time() - 604800; // 1 uke
$last_visitors_limit = 7;
$last_visitors = ess::$b->db->query("
	SELECT up_id, up_name, up_access_level, time
	FROM users_views JOIN users_players ON up_id = uv_visitor_up_id
	WHERE uv_up_id = $player->id AND time > $expire
	ORDER BY time DESC LIMIT $last_visitors_limit");
$last_visitor_anon = $player->data['up_profile_anon_time'] && $player->data['up_profile_anon_time'] > $expire ? $player->data['up_profile_anon_time'] : false;


// sett opp navnet
$name = htmlspecialchars($player->data['up_name']);

// drept?
if ($player->data['up_access_level'] == 0 && $player->data['up_deactivated_dead'] != 0)
{
	$name .= ' <span class="c_deactivated">[Død]</span>';
}

else
{
	$types = access::types($player->data['up_access_level']);
	if (!in_array("none", $types))
	{
		$type = access::type($player->data['up_access_level']);
		$type_name = access::name($type);
		$class = access::html_class($type);
		$name .= ' <span class="'.$class.'">['.htmlspecialchars($type_name).']</span>';
	}
}


// finn ut rankplassering denne timen
$result = ess::$b->db->query("
	SELECT COUNT(ref.uhi_up_id)+1, SUM(users_hits.uhi_points)
	FROM users_hits LEFT JOIN users_hits ref ON ref.uhi_points > users_hits.uhi_points AND ref.uhi_secs_hour = users_hits.uhi_secs_hour
	WHERE users_hits.uhi_secs_hour = ".login::get_secs_hour()." AND users_hits.uhi_up_id = $player->id
	GROUP BY users_hits.uhi_secs_hour, users_hits.uhi_up_id");
$rank_hour_pos = mysql_num_rows($result) > 0 ? (mysql_result($result, 0, 1) == 0 ? 'Ingen' : '#'.game::format_number(mysql_result($result, 0, 0))) : 'Ingen';


// pengerank
$result = ess::$b->db->query("SELECT COUNT(up_id)+1 FROM users_players WHERE up_cash+up_bank > CAST({$player->data['up_cash']} AS UNSIGNED)+CAST({$player->data['up_bank']} AS UNSIGNED) AND up_access_level < {$_game['access_noplay']} AND up_access_level != 0");
$pengeplassering = mysql_result($result, 0);
$pengerank = "Ubetydelig";
if ($pengeplassering == 1)
{
	$pengerank = $_game['cash_ranks'][0];
}
elseif ($pengeplassering <= 5)
{
	$pengerank = $_game['cash_ranks'][1];
}
elseif ($pengeplassering <= 15)
{
	$pengerank = $_game['cash_ranks'][2];
}


// antall vervet
$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_recruiter_up_id = $player->id");
$num_recruited = mysql_result($result, 0);


// html
echo (access::has("crewet", NULL, NULL, true) ? '
<h1><a href="'.$__server['relative_path'].'/min_side?up_id='.$player->id.'&amp;a=crew" title="Gå til spillerens side -- Tips: Trykk ESC+M+ESC for å gå til siden automatisk">'.htmlspecialchars($player->data['up_name']).'</a></h1>' : '
<h1>'.htmlspecialchars($player->data['up_name']).'</h1>').'
<p class="h_right">
	<a href="'.$__server['relative_path'].'/profil?random">Tilfeldig spiller</a>
	<a href="'.$__server['relative_path'].'/profil?random&amp;music" title="Tilfeldig spiller med musikk">(Musikk)</a>';


// kontakt/blokkering/melding
if (login::$logged_in && $player->data['up_access_level'] != 0 && $player->data['up_u_id'] != login::$user->id)
{
	// allerede kontakt?
	if (isset(login::$info['contacts'][1][$player->id])) echo '
	<a href="'.$__server['relative_path'].'/kontakter?del=contact&amp;id='.$player->id.'&amp;sid='.login::$info['ses_id'].'">Kontakt (-)</a>';
	else echo '
	<a href="'.$__server['relative_path'].'/kontakter?add=contact&amp;id='.$player->id.'">Kontakt (+)</a>';

	// allerede blokkert?
	if (isset(login::$info['contacts'][2][$player->id])) echo '
	<a href="'.$__server['relative_path'].'/kontakter?del=block&amp;id='.$player->id.'&amp;sid='.login::$info['ses_id'].'">Blokkert (-)</a>';
	else echo '
	<a href="'.$__server['relative_path'].'/kontakter?add=block&amp;id='.$player->id.'">Blokkert (+)</a>';
	
	// melding
	echo '
	<a href="'.$__server['relative_path'].'/innboks_ny?mottaker='.urlencode($player->data['up_name']).'">Send melding</a>';
}

// kontaktliste
if (login::$logged_in && count(login::$info['contacts'][1]) > 0)
{
	ess::$b->page->add_css('#kontaktliste { position: relative; top: -3px; right: -10px; margin: 0 10px 0 0; padding: 0 }');
	ess::$b->page->add_js_domready('
	$("kontaktliste").addEvent("change", function()
	{
		name = this.get("value");
		if (name == "") {
			name = prompt("Spillernavn?");
		}
		if (name != "" && name != null) {
			navigateTo(relative_path + "/p/"+escape(name));
		}
	});');
	
	echo '
	<select id="kontaktliste">';
	
	if (!isset(login::$info['contacts'][1][$player->id])) echo '
		<option>Velg kontakt</option>';
	
	// list opp kontaktene
	foreach (login::$info['contacts'][1] as $row)
	{
		echo '
		<option value="'.htmlspecialchars($row['up_name']).'"'.($row['uc_contact_up_id'] == $player->id ? ' selected="selected"' : '').'>'.htmlspecialchars($row['up_name']).'</option>';
	}
	
	echo '
		<option value="">Egendefinert..</option>
	</select>';
}

echo '
</p>';


ess::$b->page->add_css('
#profile {
	margin-bottom: 10px;
}
#profile .section {
	margin-top: 0;
	margin-bottom: 15px;
}
#profile_left {
	float: left;
	width: 70%;
}
#profile_left .section {
	margin-right: 15px;
}
#profile_right {
	float: right;
	width: 30%;
}
#profileinfo_left {
	float: left;
	width: 55%;
}
#profileinfo_right {
	float: right;
	width: 40%;
}
#profile_hr {
	margin-top: 0;
	padding-top: 0;
}');

if (login::$logged_in && login::$user->id != $player->data['up_u_id'])
{
	// sørg for at rapporteringslenkene blir prosessert
	ess::$b->page->add_js('sm_scripts.report_links();');
}

// hent andre spillere med samme navn
$pagei_other_up = new pagei(pagei::ACTIVE_GET, "side_up", pagei::PER_PAGE, 10);
$result_other_up = $pagei_other_up->query("
	SELECT up_id, up_name, up_access_level, up_created_time, up_last_online, up_points, up_deactivated_time, up_deactivated_dead, upr_rank_pos
	FROM users_players
		LEFT JOIN users_players_rank ON upr_up_id = up_id
	WHERE up_name = ".ess::$b->db->quote($player->data['up_name'])." AND up_u_id = {$player->data['up_u_id']}
	ORDER BY up_last_online DESC");
$has_other_up = mysql_num_rows($result_other_up) > 1;

// antall angrep man har utført
$attacks = $player->data['up_attack_failed_num'] + $player->data['up_attack_damaged_num'] + $player->data['up_attack_killed_num'];

echo '
<div id="profile">
	<div id="profile_left">
		<div class="section">
			<h2>Informasjon</h2>
			<p class="h_right">'.(login::$logged_in && (access::has("mod") || $player->data['up_u_id'] == login::$user->id) ? '
				<a href="'.$__server['relative_path'].'/min_side?'.(login::$user->player->id != $player->id || !login::$user->player->active ? 'up_id='.$player->id.'&amp;' : '').'a=profil">Rediger</a>' : '').(isset($_GET['signature']) ? '
				<a href="'.$__server['relative_path'].'/p/'.rawurlencode($player->data['up_name']).'/'.$player->id.'">Vis profil</a>' : '
				<a href="'.$__server['relative_path'].'/p/'.rawurlencode($player->data['up_name']).'/'.$player->id.'?signature">Vis signatur</a>').(login::$logged_in && login::$user->id != $player->data['up_u_id'] ? '
				<a href="'.$__server['relative_path'].'/js" class="report_link" rel="profile,'.$player->id.',1">Rapporter profil</a>' : '').'
			</p>
			<div id="profileinfo_left">
				<p class="c"><img src="'.htmlspecialchars($player->get_profile_image()).'" alt="Profilbilde" class="profile_image" /></p>
				<dl class="dd_right">
					<dt>Navn</dt>
					<dd>'.$name.'</dd>
					
					<dt>Registrert</dt>
					<dd onmouseover="handleClass(\'div.pr1\', \'div.pr2\', null, this)" onmouseout="handleClass(\'div.pr2\', \'div.pr1\', null, this)">
						<div class="hide pr1">'.game::timespan($player->data['up_created_time'], game::TIME_ABS | game::TIME_FULL).'</div>
						<div class="pr2">'.ess::$b->date->get($player->data['up_created_time'])->format().'</div>
					</dd>
					
					<dt>Sist aktiv</dt>
					<dd onmouseover="handleClass(\'div.psa1\', \'div.psa2\', null, this)"  onmouseout="handleClass(\'div.psa2\', \'div.psa1\', null, this)">
						<div class="hide psa1">'.ess::$b->date->get($player->data['up_last_online'])->format(date::FORMAT_SEC).'</div>
						<div class="psa2">'.($player->data['up_last_online'] ? game::timespan($player->data['up_last_online'], game::TIME_ABS | game::TIME_FULL) : 'Aldri').'</div>
					</dd>
					
					<dt>Rank</dt>
					<dd>'.$player->rank['name'].($player->rank['orig'] ? ' ('.$player->rank['orig'].')' : '').'</dd>
					
					<dt>Prestasjonspoeng</dt>
					<dd>'.game::format_num($player->data['up_achievements_points']).'</dd>'.($attacks == 0 ? '' : '
					
					<dt>Utført <b>'.$attacks.'</b> angrep, hvorav '.fwords("<b>%d</b> spiller", "<b>%d</b> spillere", $player->data['up_attack_killed_num']+$player->data['up_attack_bleed_num']).' har blitt drept og '.fwords("<b>%d</b> spiller", "<b>%d</b> spillere", $player->data['up_attack_damaged_num']-$player->data['up_attack_bleed_num']).' har blitt skadet.</dt>').'
				</dl>
			</div>
			<div id="profileinfo_right">
				<dl class="dd_right">
					<dt>Pengenivå</dt>
					<dd><a href="'.ess::$s['rpath'].'/node/27">'.game::cash_name($player->data['up_cash']+$player->data['up_bank']).'</a></dd>
					
					<dt>Pengerank</dt>
					<dd><a href="'.ess::$s['rpath'].'/node/27">'.$pengerank.'</a></dd>
					
					<dd>&nbsp;</dd>
					
					<dt>Wanted nivå</dt>
					<dd>'.game::format_number($player->data['up_wanted_level']/10, 1).' %</dd>
					
					<dd>&nbsp;</dd>
					
					<dt>Plassering</dt>
					<dd>'.game::format_number($player->data['upr_rank_pos']).'. plass</dd>
					
					<dt>Plassering denne timen</dt>
					<dd>'.$rank_hour_pos.'</dd>
					
					<dd>&nbsp;</dd>
					
					<dt>Forumtråder</dt>
					<dd>'.game::format_number($player->data['up_forum_num_topics']).($player->data['up_forum_ff_num_topics'] > 0 ? ' (<abbr title="Firma/broderskap">+'.game::format_number($player->data['up_forum_ff_num_topics']).'</abbr>)' : '').'</dd>
					
					<dt>Forumsvar</dt>
					<dd>'.game::format_number($player->data['up_forum_num_replies']).($player->data['up_forum_ff_num_replies'] > 0 ? ' (<abbr title="Firma/broderskap">+'.game::format_number($player->data['up_forum_ff_num_replies']).'</abbr>)' : '').'</dd>
					
					<dt>Meldinger (opprettet)</dt>
					<dd>'.game::format_number($player->data['up_inbox_num_threads']).'</dd>
					
					<dt>Meldinger (svar)</dt>
					<dd>'.game::format_number($player->data['up_inbox_num_messages']).'</dd>
					
					<dd>&nbsp;</dd>
					
					<dt>Antall spillere vervet</dt>
					<dd>'.game::format_number($num_recruited).'</dd>
				</dl>
			</div>
			<div class="clear"></div>
		</div>';

if ($has_other_up)
{
	if (!isset($_GET['side_up']))
	{
		ess::$b->page->add_js_domready('
	$("vis_spillerhistorie").addEvent("click", function()
	{
		$("spillerhistorie").removeClass("hide");
		this.getParent("div").dispose();
	});');
	}
	
	echo '
		<div id="spillerhistorie" class="section'.(isset($_GET['side_up']) ? '' : ' hide').'">
			<h2>Spillerhistorikk</h2>
			<table class="table '.($pagei_other_up->pages == 1 ? 'tablem' : 'tablemt').'" style="width: 100%">
				<thead>
					<tr>
						<th>Spiller</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>';
	
	while ($row = mysql_fetch_assoc($result_other_up))
	{
		$rank = game::rank_info($row['up_points'], $row['upr_rank_pos'], $row['up_access_level']);
		echo '
					<tr>
						<td>'.($player->id == $row['up_id'] ? htmlspecialchars($row['up_name']) : game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'])).'<br /><span style="font-size: 10px">'.$rank['name'].'</span></td>
						<td style="font-size: 10px">
							Opprettet: '.ess::$b->date->get($row['up_created_time'])->format().'<br />'.($row['up_access_level'] == 0 ? '
							Status: '.($row['up_deactivated_dead'] == 0 ? 'Deaktivert' : 'Død').'<br />' : '
							Status: I live<br />').'
							Sist pålogget: '.ess::$b->date->get($row['up_last_online'])->format().'
						</td>
					</tr>';
	}
	
	echo '
				</tbody>
			</table>'.($pagei_other_up->pages > 1 ? '
			<p class="c">'.$pagei_other_up->pagenumbers().'</p>' : '').'
		</div>';
}


echo '
	</div>
	<div id="profile_right">';

// hent FF
$expire = time() - 86400; // vis familier/firmaer man mistet medlemskap i siste 24 timer dersom spilleren er deaktivert
$where = !$player->active ? " AND ffm_status = ".ff_member::STATUS_DEACTIVATED." AND (ffm_date_part IS NULL OR ffm_date_part > $expire)" : " AND ffm_status = ".ff_member::STATUS_MEMBER." AND ff_inactive = 0";
$result = ess::$b->db->query("
	SELECT ffm_priority, ff_id, IFNULL(ffm_ff_name, ff_name) ffm_ff_name, ff_inactive, ff_type
	FROM ff_members JOIN ff ON ffm_ff_id = ff_id
	WHERE ffm_up_id = $player->id$where
	ORDER BY ff_type != 1, ffm_ff_name");

if (mysql_num_rows($result) > 0)
{
	echo '
		<div class="section">
			<h2>Broderskap og firma</h2>'.(!$player->active ? '
			<p>Da spilleren døde:</p>' : '').'
			<dl class="dd_right">';
	
	$i = 0;
	$mod = access::has("mod");
	while ($row = mysql_fetch_assoc($result))
	{
		$type = ff::$types[$row['ff_type']];
		$title = ' title="'.htmlspecialchars($type['typename']).'"';
		
			echo '
				<dt><a href="'.$__server['relative_path'].'/ff/?ff_id='.$row['ff_id'].'"'.$title.'>'.htmlspecialchars($row['ffm_ff_name']).'</a></dt>
				<dd>'.ucfirst($type['priority'][$row['ffm_priority']]).'</dd>';
	}
	
	echo '
			</dl>
		</div>';
}

// lenke for å vise tidligere/andre spillere
if ($has_other_up && !isset($_GET['side_up']))
{
	echo '
		<div class="section">
			<h2>Spillerhistorie</h2>
			<p>Denne spilleren har eksistert '.$pagei_other_up->total.' ganger. <a id="vis_spillerhistorie">Vis spillerhistorikk</a></p>
		</div>';
}

echo '
		<div class="section">
			<h2>Siste besøkende</h2>';

if (mysql_num_rows($last_visitors) == 0 && !$last_visitor_anon) echo '
			<p>Ingen besøkende enda.</p>';

else
{
	echo '
			<dl class="dd_right">';
	
	$anon = $last_visitor_anon ? '
				<dt>Anonym</dt>
				<dd>'.game::timespan($last_visitor_anon, game::TIME_ABS).'</dd>' : '';
	$i = 0;
	while ($row = mysql_fetch_assoc($last_visitors))
	{
		if ($i++ == $last_visitors_limit) break;
		
		// anonym?
		if ($last_visitor_anon && $last_visitor_anon > $row['time'])
		{
			echo $anon;
			$anon = false;
		}
		
		echo '
				<dt>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</dt>
				<dd>'.game::timespan($row['time'], game::TIME_ABS).'</dd>';
	}
	
	// anonym?
	if ($anon && $i <= $last_visitors_limit) echo $anon;
	
	echo '
			</dl>';
}

echo '
		</div>';


// prestasjoner
echo '
		<div class="section">
			<h2>Prestasjoner <img src="'.STATIC_LINK.'/icon/ruby.png" alt="" title="Oppnådde prestasjoner" style="vertical-align: bottom; margin-top: -2px" /></h2>';

// hent alle prestasjonene
$rep_all = $player->achievements->get_rep_count();

// sorter etter tid
$list = array();
$times = array();
foreach (achievements::$achievements as $a)
{
	// hopp over prestasjoner som ikke er utført
	if (!isset($rep_all[$a->id])) continue;
	
	$list[] = $a;
	$times[] = $rep_all[$a->id]['max_upa_time'];
}
array_multisort($times, SORT_NUMERIC, SORT_DESC, $list);

if (count($list) > 0)
{
	echo '
			<ul>';
	
	$i = 0;
	$limit = 1;
	$limit_active = false;
	foreach ($list as $a)
	{
		if ($i++ == $limit)
		{
			ess::$b->page->add_js_domready('
			document.id("prestasjoner_vis_alle").addEvent("click", function(event)
			{
				this.getParent("ul").getElements("li").setStyle("display", "");
				this.getParent("li").setStyle("display", "none");
				event.stop();
			});');
			
			$limit_active = true;
			echo '
				<li>Kun siste oppnådd vist - <a href="#" id="prestasjoner_vis_alle">vis alle</a></li>';
		}
		
		$prefix = '';
		$last = ess::$b->date->get($rep_all[$a->id]['max_upa_time'])->format(date::FORMAT_NOTIME);
		if ($a->data['ac_recurring'])
		{
			$prefix = fwords("", "%d x ", $rep_all[$a->id]['count_upa_id']);
		}
		
		echo '
				<li'.($limit_active ? ' style="display: none"' : '').'>'.$prefix.'&laquo;'.htmlspecialchars($a->data['ac_name']).'&raquo; ('.$last.')</li>';
	}
	
	echo '
			</ul>';
}

else
{
	echo '
			<p>Spilleren har ikke oppnådd noen prestasjoner.</p>';
}

echo '
		</div>';


echo '
	</div>
	<div class="clear"></div>
</div>';


// vise signaturen?
if (isset($_GET['signature']))
{
	$signature = game::bb_to_html($player->data['up_forum_signature']);
	
	ess::$b->page->add_css('
.profile_signature {
	background-color: #222222;
	margin: 1px 0 0 0;
	padding: 8px 10px 9px;
	font-size: 10px;
	text-align: center;
	overflow: hidden;
	line-height: 1.5em;
}
.profile_signature_empty {
	color: #555555;
}
');
	
	echo '
<p>Viser signaturen til '.$player->profile_link().':</p>
<div class="profile_signature">'.(empty($signature) ? '
	<span class="profile_signature_empty">Spilleren har ingen signatur.</span>' : $signature).'
</div>
<p>'.(login::$logged_in ? ($player->data['up_u_id'] == login::$user->id || access::has("forum_mod") ? '<a href="'.ess::$s['rpath'].'/min_side?up_id='.$player->id.'&amp;a=forum">Rediger signatur</a> - ' : '<a href="'.$__server['relative_path'].'/js" class="report_link" rel="signature,'.$player->id.',1">Rapporter signatur</a> - ') : '').'<a href="'.$__server['relative_path'].'/p/'.rawurlencode($player->data['up_name']).'/'.$player->id.'">Tilbake til profil</a></p>';
	
	ess::$b->page->load();
}


// forhåndsvisning?
$preview = false;
$text = $player->data['up_profile_text'];
if (isset($_POST['preview']) && (login::$logged_in && (login::$user->id == $player->data['up_u_id'] || access::has("crewet"))))
{
	ess::$b->page->add_message("Denne profilen er en forhåndsvisning av teksten du redigerer.<br /><br />For å lagre teksten må du lukke dette vinduet og gå tilbake til redigeringen.");
	$text = $_POST['preview'];
	$preview = true;
}

$html = game::format_data($text, "profile", $player);

echo '
<div class="p" id="profile_text">
	'.(empty($html) ? '<span class="dark">Mangler profiltekst.</span>' : $html).'
</div>
<div class="clear"></div>';

if ($preview)
{
	echo '
<p class="dark" style="border-top: 2px solid #1F1F1F; padding: 5px 2px"><b>Dette er en forhåndsvisning.</b></p>';
}

// sjekk for whatpulse
$wp = new whatpulse();
if ($wp->load_user($player->id) && $wp->update())
{
	// hent ut hvilke felt vi skal vise
	$fields = $wp->params->get("fields");
	if (empty($fields)) $fields = array(); //"UserID,AccountName,GeneratedTime,DateJoined,TotalKeyCount,AvKPS,TotalMouseClicks,AvCPS";
	else $fields = explode(",", $fields);
	
	// har vi noe å vise?
	if (count($fields) > 0)
	{
		ess::$b->page->add_css('.wp dl { margin: 8px 0 }');
		
		// vis info
		echo '
<div class="section wp" style="width: 250px; margin-left: auto; margin-right: auto">
	<h3><a href="http://whatpulse.org/'.$wp->user_id.'">WhatPulse informasjon</a></h3>
	<p class="h_right"><a href="'.$__server['relative_path'].'/diverse/whatpulse/">Legg til i profil</a></p>
	<dl class="dl_40">';
		
		foreach ($fields as $fieldname)
		{
			$info = $wp->stat_info($fieldname);
			
			echo '
		<dt>'.$info[0].'</dt>
		<dd>'.$info[1].'</dd>';
		}
		
		echo '
	</dl>
</div>';
	}
}

ess::$b->page->load();
