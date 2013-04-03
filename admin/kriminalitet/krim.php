<?php

require "../../base.php";
global $_base, $_game;

// hent krim info
$id = intval($_GET['id']);
$result = $_base->db->query("SELECT id, b_id, name FROM kriminalitet WHERE id = $id");
if (mysql_num_rows($result) == 0)
{
	$_base->page->add_message("Fant ingen krim med id: $id!", "error");
	redirect::handle("");
}
$krim = mysql_fetch_assoc($result);
$bydel = &game::$bydeler[$krim['b_id']];

// hent statistikk: antall brukere forsøkt, antall forsøk totalt, antall vellykkede, antall brukere med vellykkede
$result = $_base->db->query("SELECT COUNT(ks_up_id) num_users, SUM(count) sum_count, SUM(success) sum_success, COUNT(IF(success > 0, 1, NULL)) num_success FROM kriminalitet_status WHERE krimid = {$krim['id']}");
$stats = mysql_fetch_assoc($result);

// tittelen
$_base->page->add_title($krim['name']);

// endre tekster
if (isset($_POST['change_texts']))
{
	// noen å slette?
	if (isset($_POST['slett']) && is_array($_POST['slett']))
	{
		$slett = array();
		foreach ($_POST['slett'] as $id)
		{
			$slett[] = intval($id);
		}
		$_base->db->query("DELETE FROM kriminalitet_text WHERE FIND_IN_SET(id, '".implode(",", $slett)."')");
		$_base->page->add_message(count($slett).' tekst(er) ble slettet!');
	}
	
	// noen å legge til?
	if (isset($_POST['success']) && is_array($_POST['success']))
	{
		$add = array();
		foreach ($_POST['success'] as $text)
		{
			if (!empty($text))
				$add[] = "({$krim['id']}, 1, '".addslashes($text)."')";
		}
		if (count($add) > 0)
		{
			$_base->db->query("INSERT INTO kriminalitet_text (krimid, outcome, text) VALUES ".implode(",", $add));
			$_base->page->add_message(count($add).' vellykkede tekst(er) ble lagt till.');
		}
	}
	if (isset($_POST['failure']) && is_array($_POST['failure']))
	{
		$add = array();
		foreach ($_POST['failure'] as $text)
		{
			if (!empty($text))
				$add[] = "({$krim['id']}, 2, '".addslashes($text)."')";
		}
		if (count($add) > 0)
		{
			$_base->db->query("INSERT INTO kriminalitet_text (krimid, outcome, text) VALUES ".implode(",", $add));
			$_base->page->add_message(count($add).' mislykkede tekst(er) ble lagt till.');
		}
	}
	
	// send til samme siden
	$_base->page->add_message("Endringene ble lagret.");
	redirect::handle("krim?id={$krim['id']}");
}


// rediger oppføring?
if (isset($_POST['title']))
{
	$title = trim(postval("title"));
	
	if (strlen($title) < 5)
	{
		$_base->page->add_message("Tittelen må være på 4 eller flere tegn.", "error");
	}
	
	else
	{
		// sjekk bydelen
		$b_id = intval(postval("b_id"));
		
		// finnes bydelen?
		if (!isset(game::$bydeler[$b_id]))
		{
			$_base->page->add_message("Bydelen du valgte finnes ikke. Bruker opprinnelig.", "error");
			$b_id = $krim['b_id'];
		}
		
		// oppdater
		$_base->db->query("UPDATE kriminalitet SET name = ".$_base->db->quote($title).", b_id = $b_id WHERE id = {$krim['id']}");
		$_base->page->add_message("Kriminaliteten ble oppdatert.");
		redirect::handle("krim?id={$krim['id']}");
	}
}

echo '
<h1>Kriminalitet</h1>
<p class="h_right">
	<a href="./">Tilbake til krimpanel</a>
	<a href="../">Tilbake til administrasjon</a>
</p>
<form action="krim?id='.$krim['id'].'" method="post">
	<div class="section w300">
		<h2>Oppføring</h2>
		<dl class="dd_right">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title", $krim['name'])).'" class="styled w200" /></dd>
			<dt>Bydel</dt>
			<dd>
				<select name="b_id">';

foreach (game::$bydeler as $id => $row)
{
	echo '
					<option value="'.$id.'"'.($id == $krim['b_id'] ? ' selected="selected"' : '').'>'.htmlspecialchars($row['name']).'</option>';
}

echo '
				</select>
			</dd>
		</dl>
	</div>
	<p class="c">'.show_sbutton("Oppdater oppføring").'</p>
</form>
<div class="section w300">
	<h2>Statistikk</h2>
	<dl class="dd_right">
		<dt>Antall brukere forsøkt</dt>
		<dd>'.game::format_number($stats['num_users']).'</dd>
		<dt>Antall brukere med vellykkede</dt>
		<dd>'.game::format_number($stats['num_success']).($stats['num_users'] > 0 ? ' ('.game::format_number($stats['num_success']/$stats['num_users']*100, 1).' %)' : '').'</dd>
		<dt>Totalt antall forsøk</dt>
		<dd>'.game::format_number($stats['sum_count']).'</dd>
		<dt>Totalt antall vellykkede</dt>
		<dd>'.game::format_number($stats['sum_success']).($stats['sum_count'] > 0 ? ' ('.game::format_number($stats['sum_success']/$stats['sum_count']*100, 1).' %)' : '').'</dd>
	</dl>
</div>';

// hent tekstene for denne krim
$result = $_base->db->query("SELECT id, outcome, text FROM kriminalitet_text WHERE krimid = {$krim['id']} ORDER BY outcome, text");
$texts = array(
	"success" => array(),
	"failure" => array()
);
while ($row = mysql_fetch_assoc($result))
{
	if ($row['outcome'] == 1)
	{
		$texts["success"][] = $row;
	}
	elseif ($row['outcome'] == 2)
	{
		$texts["failure"][] = $row;
	}
}

$_base->page->add_js('
var krim = {
	edit: function(elm, section, id, text)
	{
		// sett som slettet
		elm.parentNode.parentNode.getElementsByTagName("input")[0].checked = true;
		
		// legg til som ny
		this.create(section, unescape(text));
	},
	remove: function(elm)
	{
		var tr = elm.parentNode.parentNode;
		tr.parentNode.removeChild(tr);
	},
	create: function(section, text)
	{
		var tbody = $(section+"_body");
		var tr = document.createElement("tr");
		
		// slette boksen
		var td = document.createElement("td");
		td.innerHTML = \'<input type="button" onclick="krim.remove(this)" value="Slett" />\';
		tr.appendChild(td);
		
		// tekstboksen
		td = document.createElement("td");
		td.innerHTML = \'<textarea name="\'+section+\'[]" rows="5" style="width: 215px"></textarea>\';
		
		// tekst?
		if (text)
		{
			td.firstChild.value = text;
		}
		
		tr.appendChild(td);
		
		// legg til raden
		tbody.appendChild(tr);
	}
};
');


echo '
<h2>Tekster</h2>
<form action="krim?id='.$krim['id'].'" method="post">';


// vellykkede tekster
echo '
	<div class="section center w300">
		<h2>Vellykkede tekster</h2>
		<p class="h_right"><a href="javascript:void(0)" onclick="krim.create(\'success\')">Ny oppføring</a></p>
		<p>Bruk dette for å erstatte tekst:</p>
		<dl class="dd_right">
			<dt>%cash</dt>
			<dd>med gevinst (## kr)</dd>
			<dt>%points</dt>
			<dd>med rankpoeng (##)</dd>
			<dt>%rank</dt>
			<dd>med rank (## %)</dd>
		</dl>
		<table class="table tablemb" width="100%">
			<thead>
				<tr>
					<th width="50">Slett</th>
					<th>Tekst</th>
				</tr>
			</thead>
			<tbody id="success_body">';

foreach ($texts['success'] as $row)
{
	echo '
				<tr>
					<td class="c"><input type="checkbox" name="slett[]" value="'.$row['id'].'" /></td>
					<td>
						'.game::bb_to_html($row['text']).'
						<a href="javascript:void(0)" onclick="krim.edit(this, \'success\', '.$row['id'].', \''.rawurlencode($row['text']).'\')" class="op50"><img src="'.STATIC_LINK.'/other/edit.gif" alt="endre" /></a>
					</td>
				</tr>';
}

echo '
			</tbody>
		</table>
	</div>';


// mislykkede tekster
echo '
	<div class="section center w300">
		<h2>Mislykkede tekster</h2>
		<p class="h_right"><a href="javascript:void(0)" onclick="krim.create(\'failure\')">Ny oppføring</a></p>
		<table class="table tablem" width="100%">
			<thead>
				<tr>
					<th width="50">Slett</th>
					<th>Tekst</th>
				</tr>
			</thead>
			<tbody id="failure_body">';

foreach ($texts['failure'] as $row)
{
	echo '
				<tr>
					<td class="c"><input type="checkbox" name="slett[]" value="'.$row['id'].'" /></td>
					<td>
						'.game::bb_to_html($row['text']).'
						<a href="javascript:void(0)" onclick="krim.edit(this, \'failure\', '.$row['id'].', \''.rawurlencode($row['text']).'\')" class="op50"><img src="'.STATIC_LINK.'/other/edit.gif" alt="endre" /></a>
					</td>
				</tr>';
}

echo '
			</tbody>
		</table>
	</div>';

// knapp
echo '
	<p class="c">'.show_sbutton("Lagre", 'name="change_texts"').'</p>
</form>';

$_base->page->load();