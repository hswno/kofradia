<?php

require "../base.php";

// send til korrekt side
redirect::handle("?a=show&su_id=".intval(getval("id")));