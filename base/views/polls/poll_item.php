<?php

// data:
// $poll
// $vote

$periode = '';
$end = empty($poll->data['p_time_end']) ? 'ubestemt' : ess::$b->date->get($poll->data['p_time_end'])->format();

if (empty($poll->data['p_time_start']))
{
	$periode = 'Til '.$end;
}
else
{
	$inprogress = $poll->data['p_time_end'] > time() ? ' (pågår)' : '';
	$periode = 'Fra '.ess::$b->date->get($poll->data['p_time_start'])->format().' til '.$end.$inprogress;
}

echo '
<div class="bg1_c xsmall">
	<h2 class="bg1">'.htmlspecialchars($poll->data['p_title']).'<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<p><b>Periode:</b><br />'.$periode.'</p>
		<p><b>Antall stemmer:</b> '.game::format_number($poll->votes).'</p>';

if (($bb = game::format_data($poll->data['p_text'])) != "")
{
	echo '
		<div class="p">'.$bb.'</div>';
}

echo '
		<div class="poll_options">';

// finn alternativet med flest stemmer
$max = 0;
foreach ($poll->options as $option)
{
	if ($option->data['po_votes'] > $max) $max = $option->data['po_votes'];
}

// alternativene
foreach ($poll->options as $option)
{
	$p = $poll->votes == 0 ? 0 : round($option->data['po_votes'] / $poll->votes * 100, 1);
	$p_w = $max == 0 ? 0 : round($option->data['po_votes'] / $max * 100, 1);
	$is = $vote == $option;
	
	// resultatet
	echo '
			<div class="poll_option'.($is ? ' voted' : '').'">
				<div class="p">'.game::format_data($option->data['po_text']).($is ? ' (valgt)' : '').'</div>
				<div class="poll_option_bar_wrap" style="width: 150px">
					<div class="poll_option_bar" style="width: '.round($p_w).'%"><p>'.$p.' %'.($is ? ' (valgt)' : '').'</p></div>
				</div>
			</div>';
}

echo '
		</div>
	</div>
</div>';