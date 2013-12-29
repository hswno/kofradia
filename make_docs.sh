#!/bin/bash
# Dette scriptet genererer phpDoc som vises på
# https://kofradia.no/docs/

cd /var/www/kofradia.no/docs/

# Oppdater koden
git pull || exit

# Oppdater Composer-filene
composer install || exit

# Kjør phpDocumentor
php vendor/bin/phpdoc.php
