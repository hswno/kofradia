<?php

session_start();
if (!$_SESSION['userinfo']) die("Du er ikke logget inn eller har vært inaktiv over lengre tid!");
require "../../../public_html/base/essentials.php";
global $_base;

if (!isset($_GET['BrukerID']))
{
	$BrukerID = $_SESSION['userinfo']['info']['id'];
}
else
{
	$BrukerID = intval($_GET['BrukerID']);
	if ($BrukerID != $_SESSION['userinfo']['info']['id'] && !in_array("mod", $_SESSION['userinfo']['accesses'])) die("Du må være moderator eller høyere for å vise andre brukere!");
}

$result = $_base->db->query("SELECT id, user, ip, hits, last_online FROM users WHERE id = $BrukerID");

if (!($user = mysql_fetch_assoc($result)))
{
	die("Fant ikke brukeren!");
}

$stats = array();
for ($i = 0; $i <= 23; $i++)
{
	$stats[$i] = 0;
}


// hent timestatistikk
$result = $_base->db->query("SELECT HOUR(FROM_UNIXTIME(secs_hour)) AS hour, SUM(hits) AS hits FROM users_hits WHERE userid = $BrukerID GROUP BY HOUR(FROM_UNIXTIME(secs_hour))");
while ($row = mysql_fetch_assoc($result))
{
	$stats[$row['hour']] = $row['hits'];
}

$peak = max($stats);
$max_height = 150;
if (isset($_GET['Height']))
{
	$height = intval($_GET['Height']);
	if ($height >= 10 && $height <= 1000) $max_height = $height;
}


$width = 20+13*24;
$height = 80+$max_height;

$result = imagecreatetruecolor($width, $height);

// bakgrunn
$bg_color = imagecolorallocate($result, 40, 0, 40);
imagefill($result, 0, 0, $bg_color);

// overskrift
$text_color = imagecolorallocate($result, 238, 238, 238);
imagestring($result, 2, 5, 5, "Statistikk for [gjennomsnitt/dag] {$user['user']}", $text_color);


$x = 10;
$y = 30+$max_height;

$line_color = imagecolorallocate($result, 150, 150, 150);
imageline($result, 8, $y-3, $width-8, $y-3, $line_color);

$bar_color = imagecolorallocate($result, 200, 200, 200);
$bar2_color = imagecolorallocatealpha($result, 150, 150, 150, 100);
$hits_color = imagecolorallocate($result, 150, 150, 150);

foreach ($stats as $hour => $hits)
{
	if ($peak == 0) $bar_h = 0;
	else $bar_h = ceil($hits/$peak*$max_height);
	$bar_y1 = $y - $bar_h - 3;
	$bar_y2 = $bar_y1 + $bar_h;
	imagefilledrectangle($result, $x+1, $bar_y1, $x+12, $bar_y2, $bar_color);
	imagefilledrectangle($result, $x+1, $bar_y2, $x+12, $bar_y2+50, $bar2_color);
	
	imagestringup($result, 1, $x+3, $y+43, str_pad(number_format($hits, 0, ",", " "), 6, " ", STR_PAD_LEFT), $hits_color);
	imagestring($result, 1, $x+2, $y, str_pad($hour, 2, "0", STR_PAD_LEFT), $text_color);
	
	$x += 13;
}

header("Content-Type: image/png");
imagepng($result);

?>
