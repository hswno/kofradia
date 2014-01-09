# KOFRADIA

http://kofradia.no/

Kopiering fra dette prosjektet er ikke tillatt. Se `LICENSE` for nærmere detaljer.

## Oppsett av utviklertjener

Denne veiledningen er uferdig.

### Composer
Kofradia bruker Composer til å hente inn tredjepartsbibliotek og for å gjøre
enkelte oppgaver i systemet.

Se http://getcomposer.org/doc/00-intro.md#installation-nix for info om å sette opp Composer.

Når Composer er satt opp må vi laste inn systemet, og må derfor kjøre install-kommandoen i rotmappa, f.eks. slik:
```php composer.phar install```

## Dokumentasjon
Dokumentasjon i utgangspunkt med phpDoc genereres hver natt kl 03, og ellers ved behov, og er tilgjengelig her:
https://kofradia.no/docs/

## Avhengigheter

* UglifyCSS
 * ```npm install -g uglifycss```
