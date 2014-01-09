#!/bin/bash

# Dette scriptet symlinkes til ../
# og kalles i ../ slik:
# ./deploy.sh

c=`pwd`
if [[ "$c" != "/var/www/kofradia.no" ]]; then
	echo "Du må være i /var/www/kofradia.no for å kjøre dette scriptet!"
	exit 1
fi

for x in kofradia static imgs wiki crewstuff
#../streetzmafia.net/html
do
	echo -e "\nPulling $x"
	cd $x
	git pull
	cd $c
done

# Vi kjører 'composer install' i stedet for 'composer update',
# slik at den installerer pakkene spesifisert i composer.lock
#
# Hvis pakker skal oppdateres til nyere versjoner skal
# 'composer update' kjøres på utviklerserver, testes og så
# depoyes ved å commite ny composer.lock og kjøre dette scriptet
# på produksjonsserveren
#
# Se også http://adamcod.es/2013/03/07/composer-install-vs-composer-update.html

cd kofradia
composer install --no-dev --optimize-autoloader

./phinx migrate

./refresh.sh
cd ..

php /var/www/kofradia.no/kofradia/app/scripts/finished_pull_all.php
