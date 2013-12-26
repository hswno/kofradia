<?php

require "../base.php";
global $_base, $__server;

access::no_guest();
$_base->page->add_title("BB-testing");

$_base->page->add_js_domready('
	new Element("input", {"type": "button", "value": "Vis resultat med AJAX", "class": "button"}).addEvent("click", function()
	{
		$("ajaxcontainer").setStyle("display", "block");
		$("ajaxcontent").set("html", "<p>Henter data..</p>");
		preview($("textcontent").value, $("ajaxcontent"));
		
		//preview_bb(event, $("textcontent").value, ["ajaxcontainer"], "ajaxcontent");
	}).inject($("ajaxbutton"));');

echo '
<div class="bg1_c large">
	<h1 class="bg1">BB-testing<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Her kan du teste diverse BB-koder. Du kan også forhåndsvise profilen din på <a href="'.$__server['relative_path'].'/min_side?a=profil">rediger profil</a>.</p>
		<form action="" method="post">
			<p><b>BB-kode</b>: (<a href="'.ess::$s['relative_path'].'/node/11">Hjelp</a>)</p>
			<p><textarea name="bb" rows="13" cols="100" style="width: 97%" id="textcontent">'.htmlspecialchars(postval("bb")).'</textarea></p>
			<p class="c">'.show_sbutton("Vis resultat").' <span id="ajaxbutton"></span></p>
		</form>
	</div>
</div>

<div class="bg1_c large'.(!isset($_POST['bb']) ? ' hide' : '').'" id="ajaxcontainer">
	<h1 class="bg1">Resultat<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<div class="p" id="ajaxcontent">'.game::bb_to_html(postval("bb")).'</div>
	</div>
</div>';

$_base->page->load();