<?php

require "../../app/ajax.php";
ajax::require_user();

// mangler brukerid?
if (!isset($_POST['up_id']))
{
	$_POST['up_id'] = 1;
}

global $__server, $_game, $_base;
$mod = access::has("mod");
$up_id = intval($_POST['up_id']);

// hent spillerdata
$result = $_base->db->query("
	SELECT
		users_players.*,
		up_cash + up_bank AS money,
		u_email, u_online_ip,
		upr_rank_pos
	FROM users_players
		LEFT JOIN users_players_rank ON upr_up_id = up_id,
		users
	WHERE up_id = $up_id AND up_u_id = u_id
	GROUP BY up_id");

if (!($player = mysql_fetch_assoc($result)))
{
	ajax::text("ERROR:404-USER", ajax::TYPE_404);
}


// hent FF
$result = ess::$b->db->query("
	SELECT ffm_priority, ff_id, ff_name, ff_type
	FROM ff_members JOIN ff ON ffm_ff_id = ff_id
	WHERE ffm_up_id = $up_id AND ffm_status = 1 AND ff_inactive = 0
	ORDER BY ff_name");
$ff = array();
while ($row = mysql_fetch_assoc($result))
{
	$type = ff::$types[$row['ff_type']];
	$row['posisjon'] = ucfirst($type['priority'][$row['ffm_priority']]);
	$ff[] = $row;
}

// profilbildet
$profile_image = player::get_profile_image_static($player['up_profile_image_url']);

// ranken
$rank = game::rank_info($player['up_points'], $player['upr_rank_pos'], $player['up_access_level']);
#$rank_name = $rank['name'];

// statusen
$status = "";

// drept?
if ($player['up_access_level'] == 0 && $player['up_deactivated_dead'] != 0)
{
	$status = '<span class="c_deactivated">[Død]</span>';
}

else
{
	$types = access::types($player['up_access_level']);
	if (!in_array("none", $types))
	{
		$type = access::type($player['up_access_level']);
		$type_name = access::name($type);
		$class = access::html_class($type);
		$status = '<span class="'.$class.'">['.htmlspecialchars($type_name).']</span>';
	}
	
	// nostat?
	if ($player['up_access_level'] >= ess::$g['access_noplay'] && $player['up_access_level'] != ess::$g['access']['nostat'][0])
	{
		if ($status != "") $status .= " ";
		$status .= '[nostat]';
	}
}

// kontakt og blokkering
$is_contact = -1;
$is_block = -1;

if ($player['up_access_level'] != 0 && $player['up_u_id'] != login::$user->id)
{
	// er kontakt?
	$is_contact = isset(login::$info['contacts'][1][$player['up_id']]) ? 1 : 0;
	
	// er blokkert?
	$is_block = isset(login::$info['contacts'][2][$player['up_id']]) ? 1 : 0;
}

// som html?
if (isset($_POST['html']))
{
	header("Content-Type: text/html; charset=utf-8");
	echo '<div class="profile_box_wrap">
	<div class="profile_box_left">
		<div class="profile_box_status">
			<p>'.$player['up_name'].($mod ? ' <span class="profile_box_type">('.$player['up_id'].')</span>' : '').($status == "" ? '' : ' '.$status).'</p>
		</div>
		<div class="profile_box_info">
			<p><span class="profile_box_type">Rank:</span> <span class="profile_box_value">'.htmlspecialchars($rank['name'].($rank['orig'] ? ' ('.$rank['orig'].')' : '')).'</span></p>'.($player['up_access_level'] != 0 ? '
			<p><span class="profile_box_type">Wanted nivå:</span> <span class="profile_box_value">'.game::format_number($player['up_wanted_level']/10, 1).' %</span></p>' : '').'
			<p><span class="profile_box_type">Sist aktiv:</span> <span class="profile_box_value">'.game::timespan($player['up_last_online'], game::TIME_ABS | game::TIME_PAST | game::TIME_FULL).'</span></p>
		</div>'.($mod ? '
		<div class="profile_box_info">
			<p><span class="profile_box_type">Penger:</span> <span class="profile_box_value">'.game::format_cash($player['money']).'</span></p>
			<p><span class="profile_box_type">E-post:</span> <span class="profile_box_value"><a href="'.$__server['relative_path'].'/admin/brukere/finn?email='.urlencode($player['u_email']).'" title="Finn brukere med denne e-posten">'.htmlspecialchars($player['u_email']).'</a></span></p>
		</div>' : '').'
		<div class="profile_box_links">
			<ul>
				<li><a href="'.$__server['relative_path'].'/p/'.rawurlencode($player['up_name']).'/'.$player['up_id'].'">Vis profil</a></li>'.(access::has("crewet") ? '
				<li>Min side: <a href="'.$__server['relative_path'].'/min_side?u_id='.$player['up_u_id'].'&amp;a=crew">bruker</a> | <a href="'.$__server['relative_path'].'/min_side?up_id='.$player['up_id'].'&amp;a=crew">spiller</a> | <a href="'.$__server['relative_path'].'/min_side?u_id='.$player['up_u_id'].'&amp;a=crew&amp;b=warning">ny advarsel</a></li>' : '').($is_contact != -1 ? '
				<li><a href="'.$__server['relative_path'].'/innboks_ny?mottaker='.urlencode($player['up_name']).'">Send melding</a></li>' : '');
	
	if ($is_contact != -1)
	{
		if ($is_contact == 1)
		{
			echo '
				<li><a href="'.$__server['relative_path'].'/kontakter/delete/'.$player['up_id'].'?sid='.login::$info['ses_id'].'">Fjern fra kontaktlisten</a></li>';
		}
		else
		{
			echo '
				<li><a href="'.$__server['relative_path'].'/kontakter/add/'.$player['up_id'].'">Legg til som kontakt</a></li>';
		}
		
		if ($is_block == 1)
		{
			echo '
				<li><a href="'.$__server['relative_path'].'/kontakter/delete/'.$player['up_id'].'?sid='.login::$info['ses_id'].'">Fjern fra blokkering</a></li>';
		}
		else
		{
			echo '
				<li><a href="'.$__server['relative_path'].'/kontakter/add/'.$player['up_id'].'?type=block">Blokker</a></li>';
		}
	}
	
	echo '
			</ul>
		</div>';
	
	// medlem av FF?
	if (count($ff) > 0)
	{
		echo '
		<div class="profile_box_info">
			<ul>';
		
		foreach ($ff as $row)
		{
			echo '
				<li>'.$row['posisjon'].' i <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a></li>';
		}
		
		echo '
			</ul>
		</div>';
	}
	
	if ($mod)
	{
		echo '
		<div class="profile_box_links">
			<ul>
				<li><a href="'.$__server['relative_path'].'/admin/brukere/finn?ip='.urlencode($player['u_online_ip']).'">'.htmlspecialchars($player['u_online_ip']).'</a> |
				<a href="'.$__server['relative_path'].'/admin/brukere/ip_sessions?ip='.urlencode($player['u_online_ip']).'">IP sessions</a></li>
			</ul>
		</div>';
	}
	
	echo '
	</div>
	<div class="profile_box_image">
		<p><img src="'.$profile_image.'" alt="Profilbilde" class="profile_image" /></p>
	</div>
</div>
<script type="text/javascript">
(function()
{
	var box = window.profile_box;
	var img = window.profile_box.getElement(".profile_box_image").getElement("img");
	var fn = function()
	{
		var s = img.getSize().y + (Browser.Engine.gecko ? 6 : (Browser.Engine.trident ? 10 : -6));
		if (box.getSize().y < s)
		{
			var p = Browser.Engine.trident4 ? "height" : "minHeight";
			box.setStyle(p, s);
		}
	};
	fn();
	img.addEvent("load", fn);
})();
</script>';
	
	die;
}


header("Content-Type: text/xml");

echo '<?xml version="1.0" encoding="utf-8"?>
<playerinfo>
	<up_id>'.$player['up_id'].'</up_id>
	<url>'.htmlspecialchars($__server['relative_path']."/p/".rawurlencode($player['up_name'])."/".$player['up_id']).'</url>
	<up_name>'.htmlspecialchars($player['up_name']).'</up_name>
	<up_name_display>'.htmlspecialchars(game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level'])).'</up_name_display>
	<status>'.htmlspecialchars($status).'</status>
	<reg_time_abs>'.htmlspecialchars($_base->date->get($player['up_created_time'])->format(date::FORMAT_SEC)).'</reg_time_abs>
	<reg_time_rel>'.htmlspecialchars(game::timespan($player['up_created_time'], game::TIME_ABS)).'</reg_time_rel>
	<last_online_abs>'.htmlspecialchars($_base->date->get($player['up_last_online'])->format(date::FORMAT_SEC)).'</last_online_abs>
	<last_online_rel>'.htmlspecialchars(game::timespan($player['up_last_online'], game::TIME_ABS)).'</last_online_rel>
	<activated>'.htmlspecialchars($player['up_access_level'] == 0 ? 0 : 1).'</activated>
	<profile_image>'.htmlspecialchars($profile_image).'</profile_image>
	<rank_name>'.htmlspecialchars($rank['name'].($rank['orig'] ? ' ('.$rank['orig'].')' : '')).'</rank_name>';

// moderator stæsj
if ($mod)
{
	echo '
	<ip>'.$player['u_online_ip'].'</ip>
	<email>'.htmlspecialchars($player['u_email']).'</email>
	<hits>'.$player['up_hits'].'</hits>
	<money>'.game::format_cash($player['money']).'</money>';
}

echo '
	<contact>'.$is_contact.'</contact>
	<block>'.$is_block.'</block>
	<ff_list>';

foreach ($ff as $row)
{
	echo '
		<ff id="'.$row['ff_id'].'" name="'.htmlspecialchars($row['ff_name']).'" pos="'.htmlspecialchars($row['posisjon']).'" />';
}

echo '
	</ff_list>
</playerinfo>';