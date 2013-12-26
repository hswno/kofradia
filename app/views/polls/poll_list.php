<?php

// data:
// $polls
// $pagei

if (count($polls) == 0)
{
	echo \Kofradia\View::forge("polls/no_polls");
}

else
{
	foreach ($polls as $poll)
	{
		// har vi stemt?
		$vote = login::$logged_in ? $poll->getVote() : null;

		echo \Kofradia\View::forge("polls/poll_item", array(
			"poll" => $poll,
			"vote" => $vote));
	}
}

if ($pagei->pages > 1)
{
	echo '
<p class="c">'.$pagei->pagenumbers('/polls', '/polls/_pageid_').'</p>';
}

if (access::has("forum_mod"))
{
	echo '
<p class="c" style="margin-top: 30px"><a href="'.ess::$s['relative_path'].'/polls/admin">Administrer avstemninger &raquo;</a></p>';
}