<?php

// data:
// array $events

?>
<h1 class="c">Usette hendelser i GitHub</h1>

<?php echo \Kofradia\View::forge("github/show_events", array("events" => $events, "new" => true)) ?>

<p class="c"><a href="/github">Vis liste over alle hendelser</a> | <a href="https://github.com/hswno/kofradia/pulse" target="_blank">Gå til GitHub</a></p>