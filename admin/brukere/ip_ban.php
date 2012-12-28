<?php

require "config.php";
global $__server;

access::need("mod");
ess::$b->page->add_title("IP-ban");

if (isset($_POST['ip']))
{
	$ip = ip2long(postval("ip"));
	if (!$ip)
	{
		ess::$b->page->add_message("Ugyldig IP-adresse.", "error");
	}
	
	else
	{
		$ip = to_float($ip);
		$ip_str = long2ip($ip);
		$time = intval(postval('time'));
		$begrunnelse = trim(postval('begrunnelse'));
		$interninfo = trim(postval('interninfo'));
		
		if (isset($_POST['confirm']))
		{
			// legg til oppføring
			ess::$b->db->query("INSERT INTO ban_ip SET bi_ip_start = $ip, bi_ip_end = $ip, bi_time_start = ".time().", bi_time_end = ".($time == 0 ? 'NULL' : ($time+time())).", bi_reason = ".ess::$b->db->quote($begrunnelse).", bi_info = ".ess::$b->db->quote($interninfo));
			
			// fjern mulig cache
			cache::delete("ip_ok_$ip_str");
			
			// IRC melding
			putlog("CREWCHAN", "%bNY IP-BAN:%b ".login::$user->player->data['up_name']." la til IP-ban for $ip_str ".ess::$s['spath']."/admin/brukere/ip_sessions?ip=$ip_str");
			
			ess::$b->page->add_message('IP-adressen '.$ip_str.' er nå blokkert/utestengt.');
			redirect::handle();
		}
		
		echo '
<h1>IP-ban</h1>
<p align="center" class="dark">
	Du har valgt følgende info:
</p>
<table class="table center">
	<tbody class="r">
		<tr>
			<th>IP</th>
			<td>'.$ip_str.'</td>
		</tr>
		<tr>
			<th>Utestengt til</th>
			<td>'.($time == 0 ? 'Permanent' : ess::$b->date->get($time+time())->format()).'</td>
		</tr>
		<tr>
			<th>Begrunnelse</th>
			<td>'.game::bb_to_html($begrunnelse).'</td>
		</tr>
		<tr>
			<th>Intern info</th>
			<td>'.game::bb_to_html($interninfo).'</td>
		</tr>
	</tbody>
</table>
<form action="" method="post">
	<input type="hidden" name="ip" value="'.$ip_str.'" />
	<input type="hidden" name="time" value="'.htmlspecialchars($time).'" />
	<input type="hidden" name="begrunnelse" value="'.htmlspecialchars($begrunnelse).'" />
	<input type="hidden" name="interninfo" value="'.htmlspecialchars($interninfo).'" />
	<input type="hidden" name="confirm" />
	<p align="center">
		'.show_sbutton("Godkjenn").'
	</p>
</form>';
		
		ess::$b->page->load();
	}
}


echo '
<form action="" method="post">
	<h1>Informasjon</h1>
	<table class="table center">
		<tbody>
			<tr>
				<th>IP</th>
				<td><input type="text" name="ip" class="styled w100" /></td>
			</tr>
			<tr>
				<th>Tid (sekunder)</th>
				<td><input type="text" name="time" class="styled w100" /></td>
			</tr>
		</tbody>
	<table class="table center tablem">
		<thead>
			<tr>
				<th>Begrunnelse</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>Tomt felt resulterer i ingen begrunnelse.<br /><textarea name="begrunnelse" class="styled w300"></textarea></td>
			</tr>
		</tbody>
	</table>
	<table class="table center">
		<thead>
			<tr>
				<th>Intern informasjon</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>Tomt felt resulterer i ingen intern informasjon.<br /><textarea name="interninfo" class="styled w300"></textarea></td>
			</tr>
		</tbody>
	</table>
	<h1>Utfør</h1>
	<p align="center">
		'.show_sbutton("Fortsett").'
	</p>
</form>';

ess::$b->page->load();