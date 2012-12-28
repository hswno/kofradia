<?php

$oppetid = shell_exec('uptime');

echo '
<h1>Oppetid</h1>
<p>
	'.$oppetid.'
</p>';