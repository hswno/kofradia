<?php

require "../base.php";
global $_base;

$_base->page->add_title("RSS feeds");

echo '
<h1>RSS feeds</h1>
<p>Tilgjengelige RSS feeds:</p>
<ul>
	<li><a href="forum_topics">Siste tråder i forumene</a></li>
	<li><a href="forum_replies">Siste svar i forumene</a></li>
</ul>';

$_base->page->load();
