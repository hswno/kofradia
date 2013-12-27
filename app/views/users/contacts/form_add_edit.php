<?php

// data:
// bool    $edit
// bool    $is_block
// \player $player
// \Kofradia\Users\Contact $contact (only if edit = true)

$info = $edit ? $contact->data['uc_info'] : null;

echo '
	<form action="" method="post">
		<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
		<div class="section" style="width: 270px; margin-left: auto; margin-right: auto">
			<h2>Informasjon</h2>
			<dl class="dl_30 dl_2x">
				<dt>Spiller</dt>
				<dd>'.$player->profile_link().'</dd>
				
				<dt>Type</dt>
				<dd>'.(!$is_block ? 'Kontakt' : 'Blokkering').'</dd>';

if ($edit)
{
	echo '
				<dt>Lagt til</dt>
				<dd>'.\ess::$b->date->get($contact->data['uc_time'])->format(date::FORMAT_SEC).'</dd>';
}


echo '			
				<dt>'.(!$is_block == 1 ? 'Informasjon' : 'Begrunnelse').'</dt>
				<dd>
					<textarea name="info" rows="5" cols="25" style="width: 165px" id="ptx">'.htmlspecialchars(postval("info", $info)).'</textarea>
				</dd>
				
				<dt'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdt">Forhåndsvisning</dt>
				<dd'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdd">'.(!isset($_POST['info']) || empty($_POST['info']) ? 'Tomt?!' : \game::bb_to_html($_POST['info'])).'</dd>
				<div class="clear"></div>
			</dl>
			<h3 class="c">
				'.($edit ? show_sbutton("Lagre", 'name="save"') : show_sbutton("Legg til", 'name="add"')).'
				'.show_sbutton("Avbryt", 'name="abort"').'
				'.show_sbutton("Forhåndsvis", 'name="preview" onclick="previewDL(event, \'ptx\', \'pdt\', \'pdd\')"').'
			</h3>
		</div>
	</form>';