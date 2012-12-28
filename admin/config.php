<?php

global $_base;

access::no_guest();
access::need("mod");
$_base->page->add_title("Administrasjon");