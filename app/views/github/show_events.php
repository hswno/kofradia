<?php

// helper

// data:
// array $events

// sett de opp daglig
$days = array();
foreach ($events as $event)
{
	$date = \ess::$b->date->get($event->event_time->getTimestamp());
	$days[$date->format(date::FORMAT_NOTIME)][] = $event;
}

?>

<?php foreach ($days as $day => $events): ?>
<div class="bg1_c medium">
	<h2 class="bg1"><?php echo $day ?></h2>
	<div class="bg1">
		<?php foreach ($events as $event): ?>
			<?php foreach ($event->getDescriptionHTML() as $text): ?>
				<p><?php if (isset($new)): ?><span class="ny">Ny!</span> <?php endif ?><span class="time"><?php echo $event->event_time->format("H:i") ?>:</span> <?php echo $text ?></p>
			<?php endforeach ?>
		<?php endforeach ?>
	</div>
</div>
<?php endforeach ?>