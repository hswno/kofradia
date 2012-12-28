<?php

require "../base.php";

access::need("sadmin");
crewlog::generate_action_list();