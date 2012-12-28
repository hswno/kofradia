<?php

require "../base.php";
global $_base;

$_base->page->add_title("Anti-bot test");

access::need("crewet");
$antibot = antibot::get("test", 1);
$antibot->check_required();


if (isset($_POST['inc']))
{
	$antibot->increase_counter();
	$_base->page->add_message("Telleren ble økt med 1");
	redirect::handle();
}


echo '
<h1>Anti-bot test</h1>

<p>
	Trenger ikke test.
</p>

<form action="" method="post">
	<p>
		<input type="submit" name="inc" value="Øk telleren" />
	</p>
</form>';

$_base->page->load();