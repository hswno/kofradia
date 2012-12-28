<?php

require "../base.php";

access::need("mod");
redirect::handle("ranklist?update", redirect::ROOT);