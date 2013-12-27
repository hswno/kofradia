<?php

// data:
// \Kofradia\Users\Contact $contact

$is_block = $contact->isBlock();
$player = $contact->getTargetPlayer();

echo '
	<h1>Oppdater '.(!$is_block ? 'kontakt' : 'blokkering').'</h1>';

echo \Kofradia\View::forge("users/contacts/form_add_edit", array(
	"edit"     => true,
	"is_block" => $is_block,
	"player"   => $player,
	"contact"  => $contact));