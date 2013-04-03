<?php

require "../../base.php";
global $_base;

access::need("mod");

echo '
<h1>Logg inn sesjoner på bruker</h1>
<form action="" method="get">
	<p>
		<input type="text" name="uid" value="" class="styled w120" />
	</p>
	<p>
		'.show_sbutton("Sjekk session info").'
	</p>
</form>';

if (isset($_REQUEST['uid']))
{
	$_base->db->query("SELECT ses_id, ses_u_id, ses_active, FROM_UNIXTIME(ses_created_time) AS opprettet, FROM_UNIXTIME(ses_last_time) AS sist_aktiv, IF(ses_logout_time = 0, 'AKTIV', FROM_UNIXTIME(ses_logout_time)) AS loggut, ses_hits, ses_points, u_id, u_email, ses_ip_list, ses_last_ip, u_access_level, ses_browsers FROM sessions LEFT JOIN users ON u_id = ses_u_id WHERE ses_u_id = ".intval($_REQUEST['uid'])." ORDER BY ses_last_time", true, true);
}

$_base->page->load();