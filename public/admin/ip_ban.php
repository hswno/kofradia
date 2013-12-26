<?php

require "../base.php";
global $_base;

$_base->page->add_title("IP Ban");

if (isset($_GET['active']))
{
	$_base->db->query("SELECT bi_id, INET_NTOA(bi_ip_start) AS ip_range_start, IF(bi_ip_end=bi_ip_start,NULL,INET_NTOA(bi_ip_end)) AS ip_range_end, FROM_UNIXTIME(bi_time_start) AS time_start, FROM_UNIXTIME(bi_time_end) AS time_end, bi_reason, bi_info FROM ban_ip WHERE ISNULL(bi_time_end) OR bi_time_end >= UNIX_TIMESTAMP() ORDER BY bi_time_end = 0, bi_time_end DESC", true, true);
}

elseif (isset($_GET['inactive']))
{
	$_base->db->query("SELECT bi_id, INET_NTOA(bi_ip_start) AS ip_range_start, IF(bi_ip_end=bi_ip_start,NULL,INET_NTOA(bi_ip_end)) AS ip_range_end, FROM_UNIXTIME(bi_time_start) AS time_start, FROM_UNIXTIME(bi_time_end) AS time_end, bi_reason, bi_info FROM ban_ip WHERE bi_time_end != 0 AND bi_time_end < UNIX_TIMESTAMP() ORDER BY bi_time_end DESC", true, true);
}

else
{
	echo '
<h1>IP ban</h1>
<ul>
	<li><a href="ip_ban?active">Vis aktive IP-ban oppføringer</a></li>
	<li><a href="ip_ban?inactive">Vis gamle IP-ban oppføringer</a></li>
</ul>';
}

$_base->page->load();