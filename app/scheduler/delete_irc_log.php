<?php

// slett logg meldingene som ble slettet for mer enn 10 min siden
$expire = time() - 900;
$ant = \Kofradia\DB::get()->exec("DELETE FROM log_irc WHERE li_deleted = 1 AND li_deleted_time < $expire");

// infomelding
putlog("LOG", "log_irc: Antall oppføringer fjernet: ".game::format_number($ant));