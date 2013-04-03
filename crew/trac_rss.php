<?php

require "config.php";

function goto_trac_timeline($specific = null, $other = null, $days = null, $to = null)
{
	if (!$specific) redirect::handle("https://kofradia.no/crewstuff/trac/timeline?daysback=10&ticket=on&ticket_details=on&changeset=on&milestone=on&wiki=on", redirect::ABSOLUTE);
	
	$params = $other ? "&ticket=on&ticket_details=on&milestone=on&wiki=on" : "&changeset=on";
	$days = $days !== null ? $days : 10;
	if ($to) $params .= "&to=".ess::$b->date->get($to)->format("m/d/y H:i:s");
	
	redirect::handle("https://kofradia.no/crewstuff/trac/timeline?daysback=$days$params", redirect::ABSOLUTE);
}

// ingen side valgt?
if (!isset($_GET['show']) || ($_GET['show'] != "changeset" && $_GET['show'] != "other"))
{
	// send til timeline
	goto_trac_timeline();
}

$other = $_GET['show'] == "other";

// første besøk?
$last = login::$user->params->get("trac_last_".($other ? 'other' : 'changeset'));
login::$user->params->update("trac_last_".($other ? 'other' : 'changeset'), time(), true);
if (!$last)
{
	goto_trac_timeline(true, $other);
}

// finn tidspunkt for neste usette hendelse
global $_trac_rss;
@include_once ROOT."/base/data/trac_rss.php";
if (isset($_trac_rss))
{
	// vis hendelsene
	$l = $last;
	foreach ($_trac_rss['data_'.($other ? 'other' : 'changeset')] as $item)
	{
		if ($item['time'] <= $last)
		{
			break;
		}
		$l = $item['time'];
	}
	$last = $l;
}

// antall dager vi skal vise (sett avvik i tidspunkt så vi kan beregne antall dager uavhengig av sommertid)
$start = ess::$b->date->get($last)->setTime(3, 0, 0);
$end = ess::$b->date->get()->setTime(0, 0, 0);

$days = abs(ceil(($end->format("U") - $start->format("U")) / 86400));

// send til timeline
goto_trac_timeline(true, $other, $days, $last);

#@include_once ROOT."/base/data/trac_rss.php";
#if (!isset($_trac_rss))
#$_trac_rss['last_'.($other ? 'other' : 'changeset')];