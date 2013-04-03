<?php

require "../base.php";
global $__server, $_base;

// slett profilbilder
if (isset($_POST['elm']) && is_array($_POST['elm']))
{
	$slette = array();
	
	foreach ($_POST['elm'] as $id)
	{
		$id = intval($id);
		if ($id == 0) continue;
		$slette[$id] = $id;
	}
	
	if (count($slette) == 0) redirect::handle();
	
	$result = $_base->db->query("SELECT profile_images.id, up_id, profile_images.time, profile_images.address, users_players.up_profile_image FROM profile_images LEFT JOIN users_players ON profile_images.id = up_profile_image WHERE profile_images.local = 1 AND FIND_IN_SET(profile_images.id, '".implode(",", $slette)."')");
	
	$not_found = array();
	$active = array();
	while ($row = mysql_fetch_assoc($result))
	{
		if (!file_exists(ROOT . $row['address']))
		{
			$not_found[] = $row['id'];
			if (!empty($row['up_profile_image'])) $active[] = $row['up_id'];
		}
	}
	
	// slett fra databasen
	if (count($not_found) > 0)
	{
		$_base->db->query("DELETE FROM profile_images WHERE FIND_IN_SET(id, '".implode(",", $not_found)."')");
		$oppdatert = $_base->db->affected_rows();
		$_base->page->add_message(game::format_number($oppdatert) . ' profilbilde(r) ble fjernet fra databasen.');
		
		if (count($active) > 0)
		{
			$_base->db->query("UPDATE users_players SET up_profile_image = NULL WHERE FIND_IN_SET(up_id, '".implode(",", $active)."')");
			$oppdatert = $_base->db->affected_rows();
			$_base->page->add_message(game::format_number($oppdatert) . ' spiller(e) ble oppdatert og har ikke lengre eget profilbilde.');
		}
	}
	
	redirect::handle();
}


// hent alle profilbildene fra databasen
$result = $_base->db->query("SELECT profile_images.id, profile_images.time, profile_images.address, up_profile_image FROM profile_images LEFT JOIN users_players ON up_profile_image = profile_images.id WHERE profile_images.local = 1 GROUP BY profile_images.id");

$not_found = array();
while ($row = mysql_fetch_assoc($result))
{
	if (!file_exists(ROOT . $row['address']))
	{
		$not_found[] = $row;
	}
}

if (count($not_found) > 0)
{
	$_base->page->add_js('function merk_alle(elm) { inputs = elm.form.getElementsByTagName("input"); for (var i = 0; i < inputs.length; i++) { if (inputs[i].type == "checkbox") { inputs[i].checked = elm.checked; } } }');
	
	echo '
<p align="center">
	Følgende bilder ('.count($not_found).' stk.) finnes ikke på serveren men er fortsatt lagt til i databasen:
</p>
<form action="" method="post">
	<table class="table center tablemb">
		<thead>
			<tr>
				<th><input type="checkbox" onclick="merk_alle(this)" /></th>
				<th>ID</th>
				<th>Opplastet</th>
				<th>Adresse</th>
				<th>Aktivt</th>
			</tr>
		</thead>
		<tbody>';
	
	$color = true;
	foreach ($not_found as $row)
	{
		echo '
			<tr'.($color = !$color ? ' class="color"' : '').'>
				<td><input type="checkbox" name="elm[]" value="'.$row['id'].'" /></td>
				<td>'.$row['id'].'</td>
				<td>'.$_base->date->get($row['time'])->format(date::FORMAT_SEC).'</td>
				<td><a href="'.$__server['relative_path'].htmlspecialchars($row['address']).'">'.htmlspecialchars($row['address']).'</a></td>
				<td>'.(empty($row['up_profile_image']) ? '&nbsp;' : '<b>Ja</b>').'</td>
			</tr>';
	}
	
	echo '
			<tr>
				<th colspan="6" class="c">'.show_sbutton("Slett bildene fra databasen").'</th>
			</tr>
		</tbody>
	</table>
</form>';
}

else
{
	echo '
<p align="center">
	Alle bildene som er i databasen finnes på disken!
</p>';
}


$_base->page->load();