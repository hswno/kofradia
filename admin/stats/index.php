<?php

require "../../base.php";
global $_base;

echo '

<h1>Statistikk</h1>

<p>
	Her kan du se litt mer avansert statistikk..
</p>

<p>
	<a href="daily_users">Antall brukere pålogget for hver dag og antall hits &raquo;</a>
</p>';

$_base->page->load();