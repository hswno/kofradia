<?php

// opprette [html] bb kode for html innhold
require "../base.php";
global $_base;

// kun admin
access::need("admin");
$_base->page->add_title("[html] bb kode");

echo '
<h1>[html] bb kode</h1>
<div class="section" style="margin-left:auto;margin-right:auto;width:400px">
	<h2>[html] bb kode</h2>
	<form action="" method="post">
		<p>
			<textarea name="html" rows="15" cols="40" style="width: 390px">'.htmlspecialchars(postval("html")).'</textarea>
		</p>
		<h4>'.show_sbutton("Generer BB kode").'</h4>
	</form>
</div>';

// generere kode?
if (isset($_POST['html']))
{
	$key = "smafia_raw_html";
	$p = game::html_generate_passphrase($_POST['html']);
	$text = "[html=$p]{$_POST['html']}[/html=$p]";

	echo '
<div class="section" style="margin-left:auto;margin-right:auto;width:400px">
	<h2>Resultat</h2>
	<p>
		<textarea rows="15" cols="40" style="width: 390px">'.htmlspecialchars($text).'</textarea>
	</p>
</div>';
}

$_base->page->load();