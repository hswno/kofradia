#!/bin/bash

# Dette scriptet symlinkes til ../
# og kalles i ../ slik:
# ./pull_all.sh

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

cd kofradia
composer install --no-dev --optimize-autoloader
cd ..

lynx --dump http://kofradia.serask.vpn.hsw.no/apc_clear_cache.php
date

php /var/www/kofradia.no/kofradia/app/scripts/finished_pull_all.php
