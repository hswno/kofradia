<?php

// data:
// array $friends(\Kofradia\Users\Contact, ..)
// array $blocks(\Kofradia\Users\Contact, ..)
// \sorts $friends_sort
// \sorts $blocks_sort

?>

<h1 id="kontakter">Kontakter</h1>
<p>Her er en oversikt over dine kontakter. Disse kontaktene får et eget bilde ved siden av spillernavnet når spillernavnet
	blir vist på siden. For å legge til en kontakt må du trykke på kontaktlinken øverst i profilen til vedkommende.</p>

<?php if (!$friends): ?>
<p>Du har ingen kontakter.</p>
<?php else: ?>
<?php echo \Kofradia\View::forge("users/contacts/list_group", array(
	"contacts" => $friends,
	"sort"     => $friends_sort,
	"is_block" => false)); ?>
<?php endif ?>


<h1 id="blokkeringer">Blokkeringsliste</h1>
<p>Her er en oversikt over hvem du har blokkert. Disse kontaktene kan ikke sende deg meldinger og får et bilde
	ved sidenav spillernavnet når spillernavnet blir vist på siden. For å legge til en blokkering må du trykke
	på blokkeringslinken øverst i profilen til vedkommende.</p>
<p>Begrunnelsen som er satt opp hos vedkommende vil komme opp som begrunnelse
	når en blokkert spiller forsøker å sende deg en melding og liknende.</p>

<?php if (!$blocks): ?>
<p>Du har ikke blokkert noen spillere.</p>
<?php else: ?>
<?php echo \Kofradia\View::forge("users/contacts/list_group", array(
	"contacts" => $blocks,
	"sort"     => $blocks_sort,
	"is_block" => true)); ?>
<?php endif ?>