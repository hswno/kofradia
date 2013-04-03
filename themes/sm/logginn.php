<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

global $class_browser;

// hent default theme fil hvis brukeren er logget inn
if (login::$logged_in)
{
	ess::$b->page->theme_file = "default";
	ess::$b->page->load();
}

require "include_top.php";
ess::$b->page->add_head('<link href="'.ess::$s['path'].'/themes/sm/guest.css?'.@filemtime(dirname(__FILE__)."/guest.css").'" rel="stylesheet" type="text/css" />');
ess::$b->page->add_head('<link href="'.ess::$s['path'].'/themes/sm/logginn.css?'.@filemtime(dirname(__FILE__)."/logginn.css").'" rel="stylesheet" type="text/css" />');

require "helpers.php";
$helper = new theme_helper("logginn");

ess::$b->page->add_js_domready('
	var b = $("idf");
	if (b.get("value") != "" && $("passordf"))
	{
		$("passordf").focus();
	}
	else
	{
		b.focus();
	}');

// skjule siden for robots?
if (isset($_GET['orign']))
{
	ess::$b->page->add_head('<meta name="robots" content="noindex" />');
}

// sett opp e-posten
$id = '';
if (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id']))
{
	$id = $_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id'];
	unset($_SESSION[$GLOBALS['__server']['session_prefix'].'logginn_id']);
}
$id = requestval("id", $id);

// TODO: lag en egen funksjon for dette!
ess::$b->page->add_js_domready('
	$("minid_info").addEvents({
		"mouseenter": function()
		{
			this.store("infobox", new Element("div", {
				"styles": {
					"left": this.getPosition().x + 30,
					"top": this.getPosition().y - 5
				},
				"class": "popup_info_box r3",
				"html": "<h1>Min ID</h1><p>Enkelt å logge inn! Velg en av disse alternativene:</p><ul><li>Navnet på en av dine spillere</li><li>ID-en til din bruker</li><li>E-postadressen din</li></ul></div>",
			}).set("tween", {"duration": "short"}).fade("hide").inject(document.body).fade(0.95));
		},
		"mouseleave": function()
		{
			this.retrieve("infobox").destroy();
		}
	});');


$data = '';
if (ess::$b->page->content)
{
	#$data .= '
	#	<div class="login_topcontent">'.ess::$b->page->content.'</div>';
}


$logginn = ess::$b->page->content.'
		<form action="" method="post" autocomplete="off">
			<p>
				<label for="idf">Din ID <span id="minid_info">(?)</span>:</label><br />
				<input type="text" name="id" id="idf" value="'.htmlspecialchars($id).'" />
			</p>'.(MAIN_SERVER ? '
			<p>
				<label for="passordf">Passord:</label><br />
				<input type="password" name="passord" id="passordf" />
			</p>' : '').'
			
			<div class="clear"></div>
			
			'.show_sbutton("Logg inn").'
			<a href="registrer" class="button">Registrer deg</a>
			
			<p>';

$expire = 0;
if (isset($_REQUEST['expire_type']))
{
	$val = intval($_POST['expire_type']);
	if ($val >= 0 && $val <= 2) $expire = $val;
}

$logginn .= '
				<br /><strong>Logg ut automatisk</strong><br />
				
				<input type="radio" name="expire_type" value="0" id="expire_type_0"'.($expire == 0 ? ' checked="checked"' : '').' />
				<label for="expire_type_0">Etter 15 minutter inaktivitet</label><br />
				
				<input type="radio" name="expire_type" value="1" id="expire_type_1"'.($expire == 1 ? ' checked="checked"' : '').' />
				<label for="expire_type_1">Når nettleseren lukkes</label><br />
				
				<input type="radio" name="expire_type" value="2" id="expire_type_2"'.($expire == 2 ? ' checked="checked"' : '').' />
				<label for="expire_type_2">Aldri</label><br />' . (ess::$s['https_support'] ? '
				
				<br />
				<input type="checkbox" name="secure_only" id="secure_only_box"'.(isset($_POST['secure_only']) ? ' checked="checked"' : '').' />
				<label for="secure_only_box">Benytt alltid sikker tilkobling</label>' : '') . '
			</p>
		</form>';


$data .= $helper->get_box("&raquo; Logg inn", $logginn, null, "login_box");

$data .= $helper->get_box("&raquo; Informasjon", '
		<ul>
			<li><a href="http://kofradia.no/blogg/">Blogg</a></li>
			<li><a href="'.ess::$s['rpath'].'/node">Hjelp</a></li>
			<li><a href="'.ess::$s['rpath'].'/forum/">Forum</a></li>
			<li><a href="'.ess::$s['rpath'].'/statistikk">Statistikk</a></li>
			<li><a href="'.ess::$s['rpath'].'/crewet">Crewet</a></li>
		</ul>', null, "login_info");

$data .= $helper->get_best_ranker_box();
$data .= $helper->get_forum_box();
$data .= $helper->get_livefeed_box();

echo $helper->draw_guest($data);