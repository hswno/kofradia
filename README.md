# KOFRADIA

http://kofradia.no/

Kopiering fra dette prosjektet er ikke tillatt. Se `LICENSE` for nærmere detaljer.

## Oppsett av utviklertjener

Sett opp database:

```bash
docker-compose up -d mysql
```

Hent ned siste versjon fra https://kofradia.no/crewstuff/f/fil/190-devdb-main

(Se [app/scripts/export_to_devdb.php](app/scripts/export_to_devdb.php) for å
generere ny versjon.)

```bash
docker exec -i $(docker-compose ps -q mysql) mysql -pkofradiapass kofradia <export_to_devdb.xxxxxxxx-xxxxxx.main.sql
# dette tar en del tid, så bare å vente
```

Start resten av tjenestene:

```bash
docker-compose up
```

http://localhost:8080/ peker nå på lokal installasjon. La dette kjøre i en
egen terminal. Bruk evt. opsjon `-d` for å kjøre i bakgrunnen.

Ting skal nå fungere lokalt!

http://localhost:8081/ kan brukes for database-administrasjon lokalt.
Bruk host:mysql user:root pass:kofradiapass ved innlogging.

For å laste inn ny versjon av utviklingsdatabase må forrige versjon slettes
først. Enkleste måte å gjøre det på:

```bash
# slett mysql-container og tilhørende data
docker-compose rm -s -f -v mysql

# start mysql-container i bakgrunnen
docker-compose up -d mysql
```

Hvis man har behov for et shell for å kjøre PHP-script, composer osv, kan man
koble seg til kjørende container slik:

```
docker-compose exec -u app app bash
```

## Dokumentasjon
Dokumentasjon i utgangspunkt med phpDoc genereres hver natt kl 03, og ellers ved behov, og er tilgjengelig her:
https://kofradia.no/docs/

## Avhengigheter

* UglifyCSS
 * ```npm install -g uglifycss```
