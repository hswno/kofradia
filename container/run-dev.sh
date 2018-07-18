#!/bin/sh
set -eu

su app -c '
  set -e
  echo
  echo "Installing composer dependencies"
  composer install
  echo
  echo "Setting up assets"
  [ -d public/assets/css ] && rm -R public/assets/*
  php app/scripts/assetic_dump.php
  echo
  echo "Creating required directories"
  mkdir -p app/data/gamelogs
  echo
  echo "Bootstrap finished - will start server"
  echo
'

exec apache2-foreground
