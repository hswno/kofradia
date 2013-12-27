<?php

// data:
// \player $player
// bool    $is_block

echo '
	<h1>Legg til '.(!$is_block ? 'kontakt' : 'blokkering').'</h1>';

echo \Kofradia\View::forge("users/contacts/form_add_edit", array(
	"edit"     => false,
	"is_block" => $is_block,
	"player"   => $player));