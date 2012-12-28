<?php

require "../base.php";
require_once ROOT . "/base/extra/func.save_user_backup";
global $_base;

access::need("admin");
$_base->page->add_title("User backup");

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
	$file = save_user_backup();
	$_base->page->add_message("User backup saved to <b>$file</b>");
	redirect::handle();
}

echo '
<h1>User backup</h1>
<form action="" method="post">
	<p>
		<input type="submit" value="Lagre backup" />
	</p>
</form>';

$_base->page->load();