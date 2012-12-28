<?php

// denne siden viser alle forumene
require "../base.php";

ess::$b->page->add_title("Forum");

// hent forumene
$forum = forum::get_forum_list();

echo '
<div class="bg1_c small bg1_padding">
	<h1 class="bg1">Forumoversikt<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';

if (count($forum) == 0)
{
	// ingen forum
	echo '
		<p>Det finnes ingen forum!</p>';
}
else
{
	echo '
	<ul>';
	
	foreach ($forum as $row)
	{
		echo '
		<li><a href="forum?id='.$row['fse_id'].'">'.htmlspecialchars($row['name']).'</a></li>';
	}
	
	echo '
	</ul>';
}

echo '
	</div>
</div>';

ess::$b->page->load();