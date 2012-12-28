<?php

require "../base.php";

ess::$b->page->add_js_domready('
fn = function ()
{
	var f = parseFloat($("colorfactor").get("value"));
	
	var v = this.get("value");
	if (v.substring(0, 1) == "#") v = v.substring(1);
	if (v.length > 6) { this.set("value", v.substring(0, 6)); v = this.get("value"); }
	if (v.length != 6) return;
	
	var res = "";
	for (var i = 0; i < 3; i++)
	{
		var n = (parseInt(v.substring(i*2, i*2+2), 16));
		n = Math.round(n*f);
		res += str_pad(n.toString(16).toUpperCase(), 2, "0", "left");
	}
	
	$("colorexfrom").setStyle("background-color", "#"+v);
	$("colorexto").setStyle("background-color", "#"+res);
	
	$("colorto").set("value", res);
}.bind($("colorfrom"));
$("colorfrom").addEvent("change", fn).addEvent("keyup", fn).focus();
$("colorfactor").addEvent("change", fn).addEvent("keyup", fn);');

ess::$b->page->add_css('
#colorexfrom, #colorexto {
	display: inline-block;
	margin-right: 10px;
	width: 200px;
	height: 50px;
	background-color: #111;
}');

echo '
<p>Faktor: <input type="text" class="styled w40" id="colorfactor" value="0.66" /></p>
<p>Inndata: <input type="text" class="styled w80" id="colorfrom" /></p>
<p>Utdata: <input type="text" class="styled w80" id="colorto" /></p>
<p><span id="colorexfrom"></span><span id="colorexto"></span></p>';

ess::$b->page->load();