<?php

\Kofradia\DB::get()->exec("
	UPDATE kriminalitet
	SET k_strength = k_strength * 0.8");

\Kofradia\DB::get()->exec("
	UPDATE kriminalitet_status
	SET ks_strength = ks_strength * 0.9
	WHERE ks_strength > 5");