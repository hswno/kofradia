<?php

require "../../base.php";
global $_lang, $_base, $__server;

$_base->page->add_title("Finn bruker/spiller");
$_base->page->theme_file = "doc";

// gammelt oppsett?
if (isset($_GET['id']) || isset($_GET['user']))
{
	if (isset($_GET['id'])) { $_GET['u_id'] = $_GET['id']; unset($_GET['id']); }
	if (isset($_GET['user'])) { $_GET['name'] = $_GET['user']; unset($_GET['user']); }
	
	// send til korrekt adresse
	$_base->page->add_message("Adressen du klikket på førte deg til en gammel versjon av denne siden. Du er sendt til korrekt adresse.");
	redirect::handle(game::address("finn", $_GET));
}

// felt man skal kunne vise
$fields = array("ip", "reg", "email", "onlinea", "onliner", "hits", "points", "banko", "cash", "birth");
$fields_name = array("IP-adresse", "Registrert", "E-post adresse", "Sist pålogget (abs)", "Sist pålogget (rel)", "Hits", "Rank", "Bank overføringer", "Penger", "Fødselsdato");

// lagre felt?
if (isset($_POST['fields']))
{
	// hent ajax funksjoner
	require PATH_APP."/ajax.php";
	
	// finn ut hvilke felt som skal lagres
	$list = explode(",", $_POST['fields']);
	$show = array();
	foreach ($list as $item)
	{
		if (isset($fields[$item]))
		{
			$show[] = $item;
		}
	}
	
	// lagre
	if (count($show) == 0)
	{
		login::$user->params->remove("finn_bruker_felt", true);
	}
	else
	{
		login::$user->params->update("finn_bruker_felt", implode(",", $show), true);
	}
	
	// send html kode for å oppdatere siden
	ajax::html('<p>Innstillingene ble lagret. Oppdaterer..</p><script type="text/javascript">navigateTo()</script>');
}

$_base->page->add_css('
.ipc_col_f, .ipc_col_f2 {
	float: left;
	width: 13%;
}
.ipc_col_c {
	float: left;
	width: 24%;
}
.ipc_col_l {
	float: left;
	width: 26%;
}
.ipc_col_f .section {
	margin-right: 5px;
}
.ipc_col_c .section, .ipc_col_f2 .section {
	margin-left: 5px;
	margin-right: 5px;
}
.ipc_col_l .section {
	margin-left: 5px;
}
.ipc_d { color: #FF0000 }
');

echo '
<form action="finn" method="get">
	<h1>Finn bruker/spiller - søkekriterier</h1>
	<p>Alle brukere hvor det finnes treff for kun én av feltene nedenfor blir vist. Separer flere søkeparametere med komma.</p>
	<p class="h_right">'.show_sbutton("Finn brukere").'</p>
	<div class="ipc_col_f">
		<div class="section">
			<h2>Bruker ID</h2>
			<p>Bruker ID-er:</p>
			<p><input type="text" name="u_id" value="'.htmlspecialchars(getval("u_id")).'" class="styled w200" style="width: 95%" /></p>
		</div>
	</div>
	<div class="ipc_col_f2">
		<div class="section">
			<h2>Spiller ID</h2>
			<p>Spiller ID-er:</p>
			<p><input type="text" name="up_id" value="'.htmlspecialchars(getval("up_id")).'" class="styled w200" style="width: 95%" /></p>
		</div>
	</div>
	<div class="ipc_col_c">
		<div class="section">
			<h2>Spiller</h2>
			<p>Spillernavn:</p>
			<p><input type="text" name="name" value="'.htmlspecialchars(getval("name")).'" class="styled w200" style="width: 95%" /></p>
		</div>
	</div>
	<div class="ipc_col_c">
		<div class="section">
			<h2>IP-adresse</h2>
			<p>IP-adresser:</p>
			<p><input type="text" name="ip" value="'.htmlspecialchars(getval("ip")).'" class="styled w200" style="width: 95%" /></p>
		</div>
	</div>
	<div class="ipc_col_l">
		<div class="section">
			<h2>E-postadresse</h2>
			<p>E-post adresser:</p>
			<p><input type="text" name="email" value="'.htmlspecialchars(getval("email")).'" class="styled w200" style="width: 95%" /></p>
		</div>
	</div>
	<div class="clear"></div>
</form>';




if (isset($_GET['u_id']) || isset($_GET['up_id']) || isset($_GET['name']) || isset($_GET['ip']) || isset($_GET['email']))
{
	echo '
<h1 id="scroll_here">Finn bruker/spiller - resultat</h1>';
	
	$u_id = array();
	$up_id = array();
	$up_name = array();
	$ip = array();
	$email = array();
	
	// bruker ID-er
	if (isset($_GET['u_id']) && !empty($_GET['u_id']))
	{
		$u_id = array_unique(array_map("intval", array_map("trim", explode(",", $_GET['u_id']))));
	}
	
	// spiller ID-er
	if (isset($_GET['up_id']) && !empty($_GET['up_id']))
	{
		$up_id = array_unique(array_map("intval", array_map("trim", explode(",", $_GET['up_id']))));
	}
	
	// spiller
	if (isset($_GET['name']) && !empty($_GET['name']))
	{
		$up_name = array_unique(array_map("trim", explode(",", $_GET['name'])));
	}
	
	// IP-er
	if (isset($_GET['ip']) && !empty($_GET['ip']))
	{
		$ip = array_unique(array_map("trim", explode(",", $_GET['ip'])));
	}
	
	// E-post adresser
	if (isset($_GET['email']) && !empty($_GET['email']))
	{
		$email = array_unique(array_map("trim", explode(",", $_GET['email'])));
	}
	
	if (count($u_id) == 0 && count($up_id) == 0 && count($up_name) == 0 && count($ip) == 0 && count($email) == 0)
	{
		$_base->page->add_message("Ingen søkekriterier ble funnet!");
	}
	
	else
	{
		$where = array();
		
		if (count($u_id) > 0)
		{
			$where[] = "(u_id = ".implode(" OR u_id = ", $u_id).")";
		}
		
		if (count($up_id) > 0)
		{
			$where[] = "(up_id = ".implode(" OR up_id = ", $up_id).")";
		}
		
		function like_search($value)
		{
			return strtr($value, array('_' => '\\_', '%' => '\\%', '*' => '%', '?' => '_'));
		}
		
		if (count($up_name) > 0)
		{
			$where[] = "(up_name LIKE ".implode(" OR up_name LIKE ", array_map("like_search", array_map(array($_base->db, "quote"), $up_name))).")";
		}
		
		if (count($ip) > 0)
		{
			$where[] = "(u_online_ip LIKE ".implode(" OR u_online_ip LIKE ", array_map("like_search", array_map(array($_base->db, "quote"), $ip))).")";
		}
		
		if (count($email) > 0)
		{
			$where[] = "(u_email LIKE ".implode(" OR u_email LIKE ", array_map("like_search", array_map(array($_base->db, "quote"), $email))).")";
		}
		
		// sortering
		$sort = new sorts("sort");
		$sort->append("asc", "Bruker ID", "u_id");					$sort->append("desc", "Bruker ID", "u_id DESC");
		$sort->append("asc", "Spillernavn", "up_name");				$sort->append("desc", "Spillernavn", "up_name DESC");
		$sort->append("asc", "IP-adresse", "u_online_ip");			$sort->append("desc", "IP-adresse", "u_online_ip DESC");
		$sort->append("asc", "Registrert", "up_created_time");		$sort->append("desc", "Registrert", "up_created_time DESC");
		$sort->append("asc", "E-postadresse", "u_email");			$sort->append("desc", "E-postadresse", "u_email DESC");
		$sort->append("asc", "Sist pålogget", "up_last_online");	$sort->append("desc", "Sist pålogget", "up_last_online DESC");
		$sort->append("asc", "Hits", "up_hits");					$sort->append("desc", "Hits", "up_hits DESC");
		$sort->append("asc", "Rank", "up_points");					$sort->append("desc", "Rank", "up_points DESC");
		$sort->append("asc", "Penger", "money");					$sort->append("desc", "Penger", "money DESC");
		$sort->append("asc", "Fødselsdato", "u_birth");				$sort->append("desc", "Fødselsdato", "u_birth DESC");
		$sort->append("asc", "Spiller ID", "up_id");				$sort->append("desc", "Spiller ID", "up_id DESC");
		$sort->set_active(getval("sort"), 11);
		
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 100);
		$sort_info = $sort->active();
		$result = $pagei->query("
			SELECT
				u_id, u_access_level, u_online_ip, u_email, u_birth, u_online_ip,
				up_id, up_name, up_access_level, up_hits, up_points, up_cash+up_bank AS money, up_created_time, up_last_online
			FROM users
				JOIN users_players ON up_u_id = u_id
			WHERE ".implode(" OR ", $where)."
			GROUP BY up_id
			ORDER BY {$sort_info['params']}");
		
		function ip_sessions_link($ip)
		{
			return '<a href="ip_sessions?ip='.urlencode($ip).'">'.htmlspecialchars($ip).'</a> (<a href="http://www.ripe.net/fcgi-bin/whois?form_type=simple&full_query_string=&searchtext='.urlencode($ip).'&do_search=Search">RIPE</a>)';
		}
		
		$list = array();
		if (count($u_id) > 0) $list[] = '<span class="dark">Bruker ID:</span> '.implode(", ", $u_id);
		if (count($up_id) > 0) $list[] = '<span class="dark">Spiller ID:</span> '.implode(", ", $up_id);
		if (count($up_name) > 0) $list[] = '<span class="dark">Spiller:</span> '.(count($up_name) == 0 ? '<i>Ikke valgt.</i>' : implode(", ", array_map("htmlspecialchars", $up_name)));
		if (count($ip) > 0) $list[] = '<span class="dark">IP-adresse:</span> '.(count($ip) == 0 ? '<i>Ikke valgt.</i>' : implode(", ", array_map("ip_sessions_link", $ip)));
		if (count($email) > 0) $list[] = '<span class="dark">E-post adresse:</span> '.(count($email) == 0 ? '<i>Ikke valgt.</i>' : implode(", ", array_map("htmlspecialchars", $email)));
		
		$_base->page->add_css('.ipc_sk dt { width: 150px }');
		echo '
<p>
	'.implode('<br />
	', $list).'
</p>';
		
		if ($result->rowCount() == 0)
		{
			echo '
<p>Ingen brukere ble funnet.</p>';
		}
		
		else
		{
			// finn ut hvilke felt vi skal vise
			$show = array();
			$list = explode(",", login::$user->params->get("finn_bruker_felt"));
			foreach ($list as $item)
			{
				if (isset($fields[$item]))
				{
					$show[$fields[$item]] = true;
				}
			}
			if (empty($show))
			{
				$show = array("ip" => true, "email" => true, "onliner" => true);
			}
			
			// sett opp kolonnetittelene
			$th = array();
			$th[] = '<th>U_ID '.$sort->show_link(0, 1).'</th>';
			$th[] = '<th>UP_ID '.$sort->show_link(20, 21).'</th>';
			$th[] = '<th>Spiller '.$sort->show_link(2, 3).'</th>';
			if (isset($show['ip'])) $th[] = '<th>IP-Adresse '.$sort->show_link(4, 5).'</th>';
			if (isset($show['reg'])) $th[] = '<th>Registert '.$sort->show_link(6, 7).'</th>';
			if (isset($show['email'])) $th[] = '<th>E-post '.$sort->show_link(8, 9).'</th>';
			if (isset($show['onlinea']) && isset($show['onliner'])) $th[] = '<th colspan="2">Sist pålogget '.$sort->show_link(10, 11).'</th>';
			elseif (isset($show['onlinea']) || isset($show['onliner'])) $th[] = '<th>Sist pålogget '.$sort->show_link(10, 11).'</th>';
			if (isset($show['hits'])) $th[] = '<th>Hits '.$sort->show_link(12, 13).'</th>';
			if (isset($show['points'])) $th[] = '<th>Rankpoeng '.$sort->show_link(14, 15).'</th>';
			if (isset($show['cash'])) $th[] = '<th>Penger '.$sort->show_link(16, 17).'</th>';
			if (isset($show['birth'])) $th[] = '<th>Fødselsdato '.$sort->show_link(18, 19).'</th>';
			if (isset($show['banko'])) $th[] = '<th colspan="2">Bankovf.</th>';
			
			echo '
<p>Antall brukere/spillere funnet: '.$pagei->total.'</p>
<script type="text/javascript">
function lagre_felt(root)
{
	// finn ut hvilke felt
	var elms = root.getElementsByTagName("input"), fields = [];
	for (var i = 0; i < elms.length; i++) { if (elms[i].checked) fields.push(elms[i].value); }
	fields = fields.join(",");
	
	// sett status
	root.innerHTML = "<p>Lagrer innstilling..</p>";
	
	// lagre vha. ajax
	new Request.HTML({
		"url": "finn",
		"data": { "fields": fields },
		"update": root
	})
	.addEvent("success", function() { ajax.refresh(); })
	.addEvent("failure", function(xhr) { alert("Kunne ikke lagre innstillingene: " + xhr.responseText); })
	.send();
}
</script>
<div><p>Vis felt:';
			
			foreach ($fields_name as $id => $name)
			{
				$checked = isset($show[$fields[$id]]) ? ' checked="checked"' : '';
				echo ' <input type="checkbox" name="field[]" id="field_'.$id.'" value="'.$id.'"'.$checked.' /><label for="field_'.$id.'"> '.htmlspecialchars($name).'</label>';
			}
			
			echo ' <a href="#" onclick="lagre_felt(this.parentNode.parentNode); return false">Lagre</a></p></div>
<form action="bankoverforinger" method="get">
	<table class="table nowrap" width="100%" style="font-size: 11px">
		<thead>
			<tr>
				'.implode('
				', $th).'
			</tr>
		</thead>
		<tbody>';
			
			// sett opp data
			$data = array();
			$ids = array();
			while ($row = $result->fetch())
			{
				$data[] = $row;
				$ids[] = $row['u_id'];
			}
			
			// hent inn ip-ban oppføringer
			$time = time();
			$result = \Kofradia\DB::get()->query("
				SELECT u_online_ip, bi_id, bi_reason
				FROM ban_ip
					JOIN users ON u_id IN (".implode(",", array_unique($ids)).")
				WHERE INET_ATON(u_online_ip) BETWEEN bi_ip_start AND bi_ip_end AND IF(ISNULL(bi_time_end), $time >= bi_time_start, $time BETWEEN bi_time_start AND bi_time_end)");
			$ip_bans = array();
			while ($row = $result->fetch())
			{
				$ip_bans[$row['u_online_ip']] = $row;
			}
			
			$i = 0;
			foreach ($data as $row)
			{
				// sett opp kolonnedata
				$td = array();
				$td[] = '<td class="r">'.($row['u_access_level'] == 0 ? '<span class="ipc_d" title="Brukeren er deaktivert">(D)</span> ' : '').'<a href="'.$__server['relative_path'].'/min_side?u_id='.$row['u_id'].'">'.$row['u_id'].'</a></td>';
				$td[] = '<td class="r"><a href="'.$__server['relative_path'].'/min_side?up_id='.$row['up_id'].'">'.$row['up_id'].'</a></td>';
				$td[] = '<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']).'</td>';
				if (isset($show['ip'])) $td[] = '<td><a href="ip_sessions?ip='.htmlspecialchars($row['u_online_ip']).'">IP økter</a> - <a href="http://www.ripe.net/fcgi-bin/whois?form_type=simple&full_query_string=&searchtext='.urlencode($row['u_online_ip']).'&do_search=Search">RIPE</a> - <a href="finn?ip='.urlencode($row['u_online_ip']).'">'.$row['u_online_ip'].'</a>'.(isset($ip_bans[$row['u_online_ip']]) ? ' <a href="../ip_ban?active" style="color:#FF0000" title="IP-Adresse blokkert'.(!empty($ip_bans[$row['u_online_ip']]['bi_reason']) ? ': '.htmlspecialchars($ip_bans[$row['u_online_ip']]['bi_reason']) : '').'">(Blokkert)</a>' : '').'</td>';
				if (isset($show['reg'])) $td[] = '<td>'.$_base->date->get($row['up_created_time'])->format(date::FORMAT_SEC).'</td>';
				if (isset($show['email'])) $td[] = '<td><a href="finn?email='.urlencode($row['u_email']).'">'.htmlspecialchars($row['u_email']).'</a></td>';
				if (isset($show['onlinea'])) $td[] = '<td>'.$_base->date->get($row['up_last_online'])->format(date::FORMAT_SEC).'</td>';
				if (isset($show['onliner'])) $td[] = '<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS | game::TIME_SHORT | game::TIME_NOBOLD).'</td>';
				if (isset($show['hits'])) $td[] = '<td class="r">'.game::format_number($row['up_hits']).'</td>';
				if (isset($show['points'])) $td[] = '<td class="r">'.game::format_number($row['up_points']).'</td>';
				if (isset($show['cash'])) $td[] = '<td class="r">'.game::format_cash($row['money']).'</td>';
				if (isset($show['birth']))
				{
					$birth = explode("-", $row['u_birth']);
					$td[] = '<td class="r">'.(empty($row['u_birth']) || $row['u_birth'] == "0000-00-00" ? 'Ikke registrert' : intval($birth[2]).". ".$_lang['months'][intval($birth[1])]." ".$birth[0]).'</td>';
				}
				if (isset($show['banko']))
				{
					$td[] = '<td><input type="radio" name="u1" value="'.$row['up_id'].'" /></td>';
					$td[] = '<td><input type="radio" name="u2" value="'.$row['up_id'].'" /></td>';
				}
				
				echo '
			<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
				'.implode("
				", $td).'
			</tr>';
			}
			
			echo '
		</tbody>
	</table>';
			
			// vise bankoverføringer?
			if (isset($show['banko']))
			{
				echo '
	<p>'.show_sbutton("Vis bankoverføringer").'</p>';
			}
			
			echo '
</form>';
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
<p>Navigasjon: '.$pagei->pagenumbers().'</p>';
			}
		}
	}
}

$_base->page->load();