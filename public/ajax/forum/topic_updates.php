<?php

require "../../../app/ajax.php";
ajax::essentials();

/**
 * Feilmeldinger:
 * ERROR:MISSING
 * ERROR:404-TOPIC (hvis tråden har blitt slettet)
 * 
 * Inndata:
 * id: TrådID
 * r_id_list: Liste over ID-ene til svarene vi har på siden
 * r_last_id: Siste svar-ID som er hentet
 * topic_last_edit: Når ble tråden oppdatert siste
 * [optional] get_new: Hent nye meldinger fra siste ID i ID-listen
 */

global $_base, $_game;

// mangler forum id?
if (!isset($_POST['topic_id']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}
$id = (int) $_POST['topic_id'];

// mangler når forumtråden sist ble oppdatert?
if (!isset($_POST['topic_last_edit']))
{
	ajax::text("ERROR:MISSING");
}
$topic_last_edit = (int) $_POST['topic_last_edit'];

// hent topic info
$seen_q = login::$logged_in ? "fs_ft_id = ft_id AND fs_u_id = ".login::$user->id : "FALSE";
$result = $_base->db->query("
SELECT
	ft_id, ft_fse_id, ft_title, ft_deleted, ft_last_reply, ft_last_edit,
	fs_time
FROM
	forum_topics
	LEFT JOIN forum_seen ON $seen_q
WHERE ft_id = $id");

// finnes ikke?
if (mysql_num_rows($result) == 0)
{
	ajax::text("ERROR:404-TOPIC", ajax::TYPE_INVALID);
}

// les info
$topic = mysql_fetch_assoc($result);

// sjekk om det er slettet, har vi tilgang?
if ($topic['ft_deleted'] != 0 && !access::has("forum_mod"))
{
	ajax::text("ERROR:404-TOPIC", ajax::TYPE_INVALID);
}

// kontroller tilgang til forumet
$forum = new \Kofradia\Forum\CategoryAjax($topic['ft_fse_id']);
$forum->require_access();

// mangler svarliste?
if (!isset($_POST['r_id_list']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// hvilket tidspunkt vi skal hente endringer etter
if (!isset($_POST['time']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}
$time_last = (int) $_POST['time'];

// mangler siste id?
if (!isset($_POST['r_last_id']))
{
	ajax::text("ERROR:MISSING");
}

// sett opp liste over svar-ID-ene vi har
$id_list = array_unique(array_map("intval", explode(",", $_POST['r_id_list'])));

// sett opp siste ID
$id_last = intval($_POST['r_last_id']);

// skal vi hente nye svar etter $id_last ?
$get_new = isset($_POST['get_new']);

// sjekk om noen av svarene er slettet og vi skal fjerne slettede svar
$deleted = array();
if (count($id_list) > 0 && !isset($_POST['no_delete']))
{
	$result = $_base->db->query("SELECT fr_id FROM forum_replies WHERE fr_id IN (".implode(",", $id_list).") AND fr_deleted != 0");
	while ($row = mysql_fetch_assoc($result))
	{
		$deleted[] = $row['fr_id'];
		unset($id_list[array_search($row['fr_id'], $id_list)]);
	}
}

// sjekk om noen av svarene er redigert
$updated = array();
$updated_last_edit = array();
if (count($id_list) > 0)
{
	$result = $_base->db->query("
	SELECT
		r.fr_id, r.fr_time, r.fr_up_id, r.fr_text, r.fr_last_edit, r.fr_last_edit_up_id, r.fr_deleted,
		up_name, up_access_level, up_forum_signature, up_points, up_profile_image_url, upr_rank_pos,
		r_time,
		COUNT(n.fr_id)+2 reply_num
	FROM
		forum_replies AS r
		LEFT JOIN users_players ON up_id = r.fr_up_id
		LEFT JOIN users_players_rank ON upr_up_id = up_id
		LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_FORUM_REPLY." AND r_type_id = r.fr_id AND r_state < 2
		LEFT JOIN forum_replies n ON n.fr_ft_id = r.fr_ft_id AND n.fr_id < r.fr_id AND n.fr_deleted = 0
	WHERE
		r.fr_ft_id = $id AND r.fr_deleted = 0 AND r.fr_id IN (".implode(",", $id_list).") AND r.fr_last_edit >= $time_last
	GROUP BY r.fr_id
	ORDER BY r.fr_time ASC");
	
	while ($row = mysql_fetch_assoc($result))
	{
		$data = $row;
		$data['ft_fse_id'] = $forum->id;
		$data['ft_id'] = $id;
		$data['fs_new'] = $topic['fs_time'] < $row['fr_time'] && \Kofradia\Forum\Category::$fs_check;
		
		$updated[$row['fr_id']] = $forum->template_topic_reply($data);
		
		// lagre tidspunktet for sist endret slik at man unngår å skrive over endringer uten å være klar over det når man redigerer
		$updated_last_edit[$row['fr_id']] = $data['fr_last_edit'];
	}
}

// sjekk etter nye svar
$new = array();
$new_last_edit = array();
if ($get_new)
{
	$result = $_base->db->query("
		SELECT
			r.fr_id, r.fr_time, r.fr_up_id, r.fr_text, r.fr_last_edit, r.fr_last_edit_up_id, r.fr_deleted,
			up_name, up_access_level, up_forum_signature, up_points, up_profile_image_url, upr_rank_pos,
			r_time,
			COUNT(n.fr_id)+2 reply_num
		FROM
			forum_replies AS r
			LEFT JOIN users_players ON up_id = r.fr_up_id
			LEFT JOIN users_players_rank ON upr_up_id = up_id
			LEFT JOIN rapportering ON r_type = ".rapportering::TYPE_FORUM_REPLY." AND r_type_id = r.fr_id AND r_state < 2
			LEFT JOIN forum_replies n ON n.fr_ft_id = r.fr_ft_id AND n.fr_id < r.fr_id AND n.fr_deleted = 0
		WHERE
			r.fr_ft_id = $id AND r.fr_deleted = 0 AND r.fr_id > $id_last
		GROUP BY r.fr_id
		ORDER BY r.fr_time ASC");
	
	$time_last = 0;
	while ($row = mysql_fetch_assoc($result))
	{
		$data = $row;
		$data['ft_fse_id'] = $forum->id;
		$data['ft_id'] = $id;
		$data['fs_new'] = $topic['fs_time'] < $row['fr_time'] && \Kofradia\Forum\Category::$fs_check;
		
		$new[$row['fr_id']] = $forum->template_topic_reply($data);
		$time_last = $row['fr_time'];
		
		// lagre tidspunktet for sist endret slik at man unngår å skrive over endringer uten å være klar over det når man redigerer
		$new_last_edit[$row['fr_id']] = $data['fr_last_edit'];
	}
	
	// oppdatere sist sett?
	if ($time_last && login::$logged_in)
	{
		// oppdater
		$_base->db->query("UPDATE forum_seen SET fs_time = GREATEST(fs_time, $time_last) WHERE fs_ft_id = $id AND fs_u_id = ".login::$user->id);
	}
}

// generer XML
$xml = '
<topic id="'.$id.'" time="'.time().'">';

// forumtråden oppdatert?
if (((int)$topic['ft_last_edit']) != $topic_last_edit)
{
	$topic_obj = new \Kofradia\Forum\TopicAjax($id, $forum);
	
	$xml .= '
	<tupdated last_edit="'.$topic_obj->info['ft_last_edit'].'">'.htmlspecialchars(parse_html($forum->template_topic($topic_obj->extended_info()))).'</tupdated>';
}

$xml .= '
	<new>';

// noen nye?
if (count($new) > 0)
{
	$new = parse_html_array($new);
	foreach ($new as $id => $html)
	{
		$xml .= '
		<post id="'.$id.'" last_edit="'.intval($new_last_edit[$id]).'">'.htmlspecialchars($html).'</post>';
	}
	$xml .= '
';
}

$xml .= '</new>
	<updated>';

// noen oppdaterte?
if (count($updated) > 0)
{
	$updated = parse_html_array($updated);
	foreach ($updated as $id => $html)
	{
		$xml .= '
		<post id="'.$id.'" last_edit="'.intval($updated_last_edit[$id]).'">'.htmlspecialchars($html).'</post>';
	}
	$xml .= '
';
}

$xml .= '</updated>
	<deleted>';

// noen slettede?
if (count($deleted) > 0)
{
	foreach ($deleted as $id)
	{
		$xml .= '
		<post>'.$id.'</post>';
	}
	$xml .= '
';
}

$xml .= '</deleted>
</topic>';

ajax::xml($xml);