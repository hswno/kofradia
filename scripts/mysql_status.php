<?php

require "../base.php";
global $_base;

$global = isset($_POST['noglobal']) ? "" : " GLOBAL";
$result = $_base->db->query("SHOW$global STATUS", true, true);
$variables = array();
while ($row = mysql_fetch_row($result))
{
	$variables[$row[0]] = $row[1];
}

throw new HSException($variables);

$avg = $variables['Questions'] / $variables['Uptime'];

echo "<p>Avg: $avg</p>";

$_base->page->load();