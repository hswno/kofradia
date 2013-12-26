<?php

define("ALLOW_GUEST", true);
require "../base.php";

putlog("NOTICE", "%c4%b%u{$_SERVER['REMOTE_ADDR']}%u%b viste {$_SERVER['REQUEST_URI']}");
access::need("sadmin");

$data = array();
foreach ($GLOBALS as $key => $value)
{
	if ($key == "GLOBALS" || $key == "data" || $key == "key" || $key == "value") continue;
	$data[$key] = $value;
}

ob_start();
var_dump($data);
$data = ob_get_contents();
ob_clean();

echo 'GLOBALS:
<pre>'.htmlspecialchars($data).'</pre>';