<?php

// data:
// array  $contacts
// bool   $is_block
// \sorts $sort

$type = $is_block ? 'block' : 'normal';

?>
	<form action="&rpath;/kontakter/delete" method="post">
		<input type="hidden" name="sid" value="<?php echo \login::$info['ses_id'] ?>" />
		<table class="table spacerfix center">
			<thead>
				<tr>
					<th><?php echo (!$is_block ? "Kontakt" : "Blokkert") ?> (<a href="#" class="box_handle_toggle" rel="id_<?php echo $type ?>">Merk alle</a>) <?php echo $sort->show_link(0, 1) ?></th>
					<th>Sist pålogget <?php echo $sort->show_link(2, 3) ?></th>
					<th>Lagt til <?php echo $sort->show_link(4, 5) ?></th>
					<th><?php echo (!$is_block ? "Informasjon" : "Begrunnelse") ?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>			

		<?php $i = 0;
		foreach ($contacts as $contact): ?>
				<tr class="box_handle<?php echo (++$i % 2 == 0 ? ' color' : '') ?>">
					<td><input type="checkbox" name="id[]" rel="id_<?php echo $type ?>" value="<?php echo $contact->data['uc_id'] ?>" /><?php echo \game::profile_link($contact->data['uc_contact_up_id'], $contact->data['up_name'], $contact->data['up_access_level']) ?></td>
					<td class="r"><?php echo \game::timespan($contact->data['up_last_online'], game::TIME_ABS) ?></td>
					<td class="r"><?php echo \ess::$b->date->get($contact->data['uc_time'])->format(date::FORMAT_NOTIME) ?></td>
					<td><?php echo (empty($contact->data['uc_info']) ? '<span class="dark">Ingen info</span>' : \game::bb_to_html($contact->data['uc_info'])) ?></td>
					<td><a href="kontakter/edit/<?php echo $contact->data['uc_id'] ?>" class="op50"><img src="&staticlink;/other/edit.gif" alt="Rediger" /></a></td>
				</tr>
		<?php endforeach ?>

			</tbody>
		</table>
		<p class="c">
			<?php echo show_sbutton("Fjern", 'onclick="return confirm(\'Sikker på at du vil fjerne de valgte oppføringene?\')"'); ?>
		</p>
	</form>