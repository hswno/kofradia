<?php

require "app/inc.innstillinger_pre.php";

return array(
	"paths" => array(
		"migrations" => "app/migrations"
	),
	"environments" => array(
		"default_migration_table" => "phinxlog",
		"default_database" => DBNAME,
		"production" => array(
			"adapter" => "mysql",
			"host"    => DBHOST,
			"name"    => DBNAME,
			"user"    => DBUSER,
			"pass"    => DBPASS,
			"port"    => 3306
		)
	)
);

