<?php

// data:
// $list
// $pagei

?>
<div class="bg1_c xsmall">
	<h1 class="bg1">Siste utpressinger<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_right"><a href="/node/4">Hjelp</a></p>
	<div class="bg1">
		<p class="c"><a href="/utpressing">&laquo; Tilbake</a></p>
		<p>Her kan du se utpressingene du har utført de siste 12 timene.</p>

		<?php if (!count($list)): ?>
		<p>Du har ikke utført noen utpressinger de siste 12 timene.</p>
		<?php else: ?>

		<table class="table<?php echo ($pagei->pages == 1 ? ' tablemb' : ''); ?> center">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Bydel</th>
					<th>Tidspunkt</th>
				</tr>
			</thead>
			<tbody>

			<?php $i = 0; foreach ($list as $row):
				$bydel = "Ukjent bydel";
				if (!empty($row['ut_b_id']) && isset(game::$bydeler[$row['ut_b_id']]))
				{
					$bydel = htmlspecialchars(game::$bydeler[$row['ut_b_id']]['name']);
				} ?>

				<tr<?php echo (++$i % 2 == 0 ? ' class="color"' : '') ?>>
					<td><user id="<?php echo $row['ut_affected_up_id'] ?>" /></td>
					<td><?php echo $bydel ?></td>
					<td><?php echo \ess::$b->date->get($row['ut_time'])->format() ?></td>
				</tr>
			<?php endforeach ?>

			</tbody>
		</table>

			<?php if ($pagei->pages > 1): ?>
		<p class="c">'.$pagei->pagenumbers().'</p>
			<?php endif ?>

		<?php endif ?>
	</div>
</div>