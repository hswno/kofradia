<?php

require "base.php";
global $_base;

$_base->page->add_title("Annet");

echo '
<h1>Annet</h1>

<p>
	<a href="irc/">IRC</a>
</p>';

$_base->page->load();