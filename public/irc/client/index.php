<?php

require dirname(dirname(dirname(__FILE__)))."/../app/base.php";

access::no_guest();
ess::$b->page->theme_file = "guest";
#ess::$b->page->add_css('body { width: 630px } #header a { width: 650px }');
ess::$b->page->add_title("IRC Chat");

ess::$b->page->js_disable = true;

$ircnick = str_replace(" ", "_", login::$user->player->data['up_name']);
putlog("NOTICE", "%b%c11IRC-CHAT:%c%b %u".login::$user->player->data['up_name']."%u med IRC nick %u$ircnick%u");

echo '
<h1>IRC Chat</h1>
<p>
	Hvis du ikke ønsker å laste ned og installere mIRC på din egen PC kan du bruke dette som et alternativ for å prate på chatten!
</p>
<iframe src="http://webchat.quakenet.org/?nick='.urlencode($ircnick."[KF]").'&channels=kofradia&uio=OT10cnVlJjExPTE5NQ64" width="100%" height="400"></iframe>';

ess::$b->page->add_body_post('
<script type="text/javascript">
var elms = document.getElementsByTagName("a");
for (var i = 0; i < elms.length; i++) elms[i].target = "_blank";
</script>');

ess::$b->page->load();
