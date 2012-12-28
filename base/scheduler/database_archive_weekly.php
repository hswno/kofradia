<?php

// gjennomføres kun på mandager
$day = ess::$b->date->get()->format("N");
if ($day != 1) return;

database_archive::run_weekly();