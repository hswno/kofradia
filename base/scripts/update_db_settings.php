<?php

// denne filen henter innstillingene og lagrer til cache

$result = ess::$b->db->query("SELECT id, name, value FROM settings");
game::$settings = array();
while ($row = mysql_fetch_assoc($result))
{
	game::$settings[$row['name']] = array("id" => $row['id'], "value" => $row['value']);
}

// lagre til cache
// behold for 10 minutter
cache::store("settings", game::$settings, 600);