<?php

require "../../app/ajax.php";
ajax::validate_sid();

global $_game, $__server, $_base;

// hent egen informasjon
// TODO: Velge kun det vi skal bruke?
$result = $_base->db->query("
	SELECT
		users_players.*,
		upr_rank_pos,
		users.*,
		up_cash + up_bank AS money
	FROM
		users_players
		LEFT JOIN users_players_rank ON upr_up_id = up_id,
		users
	WHERE up_id = ".login::$user->player->id." AND up_u_id = u_id
	GROUP BY up_id");

// fant ikke?
if (mysql_num_rows($result) == 0)
{
	ajax::text("ERROR:NO-USER", ajax::TYPE_404);
}

// les info
$user = mysql_fetch_assoc($result);

// profilbilde
$profile_image = parse_html(player::get_profile_image_static($user['up_profile_image_url']));

// ranken
$rank = game::rank_info($user['up_points'], $user['upr_rank_pos'], $user['up_access_level']);

// statusen
$status = "";
$types = access::types($user['up_access_level']);
if (!in_array("none", $types))
{
	$type = access::type($user['up_access_level']);
	$type_name = access::name($type);
	$class = access::html_class($type);
	$status = '<span class="'.$class.'">['.htmlspecialchars($type_name).']</span>';
}

// bydel
$bydel = game::$bydeler[$user['up_b_id']];

$health = $user['up_health'] / $user['up_health_max'] * 100;
$energy = $user['up_energy'] / $user['up_energy_max'] * 100;

// xml
ajax::xml('<userinfo u_id="'.$user['u_id'].'">
	<u_id>'.$user['u_id'].'</u_id>
	<u_reg_time_abs>'.htmlspecialchars($_base->date->get($user['u_created_time'])->format(date::FORMAT_SEC)).'</u_reg_time_abs>
	<u_reg_time_rel>'.htmlspecialchars(game::timespan($user['u_created_time'], game::TIME_ABS)).'</u_reg_time_rel>
	<u_last_online_abs>'.htmlspecialchars($_base->date->get($user['u_online_time'])->format(date::FORMAT_SEC)).'</u_last_online_abs>
	<u_last_online_rel>'.htmlspecialchars(game::timespan($user['u_online_time'], game::TIME_ABS)).'</u_last_online_rel>
	<u_ip>'.$user['u_online_ip'].'</u_ip>
	<u_email>'.htmlspecialchars($user['u_email']).'</u_email>
	<u_hits>'.$user['u_hits'].'</u_hits>
	<u_inbox_new>'.$user['u_inbox_new'].'</u_inbox_new>
	<player up_id="'.$user['up_id'].'">
		<up_id>'.$user['up_id'].'</up_id>
		<up_url>'.htmlspecialchars($__server['relative_path']."/p/".rawurlencode($user['up_name'])).'</up_url>
		<up_name>'.htmlspecialchars($user['up_name']).'</up_name>
		<up_name_display>'.htmlspecialchars(game::profile_link($user['up_id'], $user['up_name'], $user['up_access_level'])).'</up_name_display>
		<up_reg_time_abs>'.htmlspecialchars($_base->date->get($user['up_created_time'])->format(date::FORMAT_SEC)).'</up_reg_time_abs>
		<up_reg_time_rel>'.htmlspecialchars(game::timespan($user['up_created_time'], game::TIME_ABS)).'</up_reg_time_rel>
		<up_status>'.htmlspecialchars($status).'</up_status>
		<up_last_online_abs>'.htmlspecialchars($_base->date->get($user['up_last_online'])->format(date::FORMAT_SEC)).'</up_last_online_abs>
		<up_last_online_rel>'.htmlspecialchars(game::timespan($user['up_last_online'], game::TIME_ABS)).'</up_last_online_rel>
		<up_activated>'.htmlspecialchars($user['up_access_level'] == 0 ? 0 : 1).'</up_activated>
		<up_profile_image>'.htmlspecialchars($profile_image).'</up_profile_image>
		<up_log_new>'.($user['up_log_new']+$user['up_log_ff_new']).'</up_log_new>
		<up_rank_name>'.htmlspecialchars($rank['name'].($rank['orig'] ? ' ('.$rank['orig'].')' : '')).'</up_rank_name>
		<up_rank_position>'.$user['upr_rank_pos'].'</up_rank_position>
		<up_hits>'.$user['up_hits'].'</up_hits>
		<up_cash>'.game::format_cash($user['up_cash']).'</up_cash>
		<up_bank>'.game::format_cash($user['up_bank']).'</up_bank>
		<up_money>'.game::format_cash($user['money']).'</up_money>
		<up_money_title>'.game::cash_name($user['money']).'</up_money_title>
		<up_last_interest>'.game::format_cash($user['up_interest_last']).'</up_last_interest>
		<up_bydel_latitude>'.htmlspecialchars($bydel['latitude']).'</up_bydel_latitude>
		<up_bydel_longitude>'.htmlspecialchars($bydel['longitude']).'</up_bydel_longitude>
		<up_bydel_id>'.htmlspecialchars($bydel['id']).'</up_bydel_id>
		<up_bydel_name>'.htmlspecialchars($bydel['name']).'</up_bydel_name>
		<up_health>'.($health == 100 ? '100' : sprintf("%.2f", $health)).'</up_health>
		<up_energy>'.($energy == 100 ? '100' : sprintf("%.2f", $energy)).'</up_energy>
		<up_protection>'.(!$user['up_protection_id'] ? 'null' : ($user['up_protection_state'] == 1 ? '100' : sprintf("%.2f", $user['up_protection_state'] * 100))).'</up_protection>
		<up_rank>'.sprintf("%.3f", login::$user->player->rank['need_points'] == 0 ? $user['up_points'] / login::$user->player->rank['points'] * 100 : ($user['up_points']-login::$user->player->rank['points']) / login::$user->player->rank['need_points'] * 100).':'.$user['up_points'].'</up_rank>
		<up_wanted>'.($user['up_wanted_level'] == 0 ? '0' : sprintf("%.1f", $user['up_wanted_level']/10, 1)).'</up_wanted>
	</player>
	<game>
		<poker_active>'.cache::fetch("poker_active", 0).'</poker_active>
		<auksjoner_active>'.game::auksjoner_active_count().'</auksjoner_active>
		<fengsel_count>'.game::fengsel_count().'</fengsel_count>
	</game>
</userinfo>');