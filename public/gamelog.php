<?php

require "base.php";
global $_base;

redirect::handle("min_side?".(login::$user->player->active ? '' : 'up_id='.login::$user->player->id.'&') . "a=log");