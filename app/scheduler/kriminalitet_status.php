<?php

ess::$b->db->query("
	UPDATE kriminalitet
	SET k_strength = k_strength * 0.8");

ess::$b->db->query("
	UPDATE kriminalitet_status
	SET ks_strength = ks_strength * 0.9
	WHERE ks_strength > 5");