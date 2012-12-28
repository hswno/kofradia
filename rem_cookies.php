<?php

require "base/essentials.php";
global $__server;

$u = "Sletter cookies..<br />\n";

foreach ($_COOKIE as $key => $cookie)
{
	// rem cookie
	setcookie($key, "", time()-1728000, $__server['cookie_path'], $__server['cookie_domain']);
	setcookie($key, "", time()-1728000, "", $__server['cookie_domain']);
	setcookie($key, "", time()-1728000, $__server['cookie_path'], "");
	setcookie($key, "", time()-1728000, "", "");
	$u .= "cookie fjernet: $key (opprinnelig verdi: ".htmlspecialchars($cookie).")<br />\n";
}


echo $u;

echo '
<br />
Hvis du laster denne siden umiddelbart på nytt og den sletter flere (samme) cookies må du sannsynligvis slette cookies manuelt i nettleseren din.<br />
Se i så fall <a href="http://www.aboutcookies.org/Default.aspx?page=2">http://www.aboutcookies.org/Default.aspx?page=2</a>';
