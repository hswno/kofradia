<?php

// data:
// array $events
// pagei $pagei

?>
<h1 class="c">Hendelser i GitHub</h1>

<?php echo \Kofradia\View::forge("github/show_events", array("events" => $events)) ?>

<p class="c"><?php echo $pagei->pagenumbers(); ?></p>
<p class="c"><a href="https://github.com/hswno/kofradia/pulse" target="_blank">Gå til GitHub</a></p>