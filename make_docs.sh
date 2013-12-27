#!/bin/bash

# Dette scriptet genererer phpDoc som vises på
# https://kofradia.no/docs/

# Oppdater koden
git pull || exit

# Oppdater Composer-filene
composer install || exit

# Kjør phpDocumentor
php vendor/phpdocumentor/phpdocumentor/bin/phpdoc.php -d app -t public-docs
