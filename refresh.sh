#!/bin/bash

rm public/assets/* -R
php app/scripts/assetic_dump.php

lynx --dump http://kofradia.serask.vpn.hsw.no/apc_clear_cache.php
date
