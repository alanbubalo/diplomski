# Ilustrativni prototip

Prilog diplomskom radu *Obrasci tolerancije na greške u distribuiranim
sustavima: studija slučaja Fiskalizacije 2.0*. Razrađen u poglavlju 7.

## Čemu služi

Prototip postoji **da bi tvrdnje u matrici poglavlja 8 prešle iz razine (c)
prosudba autora u razinu (b) pokazano.** Ne služi za mjerenje, ne dokazuje
izvedivost proizvoda i ne pokazuje sukladnost s propisom.

Pokriva **isključivo granicu C1↔C2** iz odjeljka 6.1: poslovni sustav predaje
dokument informacijskom posredniku i mora završiti u stanju koje može
obrazložiti.

## Što nema, i zašto

Bez UBL-a, Schematrona, AS4, XAdES-a i PKI-ja, otkrivanja adrese primatelja,
chaos enginea, orkestratora i mjerenja latencije.

Posrednik je **stub**: na naredbu vraća `uspjeh`, `istek-vremena` ili `S008`.
Vjerodostojna izvedba posrednika ne bi dodala nijedan nalaz, a udvostručila bi
opseg. Predmet je rukovanje greškom, ne sukladnost.

## Pokretanje

Traži **PHP 8.5** (razvijano na 8.5.8) i Composer. Laravel 13.25, SQLite.

```bash
composer setup
```

Tri scenarija, svaki u dvije izvedbe. Razlika je u jednoj jedinoj stvari:
piše li se zapis namjere **prije** predaje ili **poslije** odgovora.

```bash
php artisan scenarij b1                 # istek vremena bez odgovora
php artisan scenarij e1                 # predaja bez potvrde
php artisan scenarij b2                 # ista šifra S008 za dva ishoda

php artisan scenarij b2 --bez-zapisa    # ista stvar bez odlaznog pretinca

php artisan stanje                      # ispis završnog stanja
php artisan test                        # 23 tvrdnje
```

Sat je pri scenariju fiksiran na `2026-09-01 09:00:00` da ispis bude ponovljiv.
Isti sat dobiva i dnevnik, pa se baza i `storage/logs/laravel.log` slažu.

## Gdje je što

| Putanja | Sadržaj |
|---|---|
| `app/Domena/Stanje.php` | automat, uključujući stanje `predano-nepotvrdeno` koje propis ne imenuje |
| `app/Domena/Automat.php` | dopušteni prijelazi; **rok kao uvjet nad prijelazom** |
| `app/Domena/TumacOdgovora.php` | jezgra slučaja B2: ista šifra, dva značenja |
| `app/Domena/RasporedPonavljanja.php` | raspored vođen preostalim rokom, uz eksponencijalni odmak za usporedbu |
| `app/Domena/Rok.php` | pet radnih dana od nastupa, čl. 49. st. 1. |
| `app/Servisi/Predavatelj.php` | odlazni pretinac; obje izvedbe |
| `database/migrations/` | tri tablice: dokument, predaja, evidencija |

## Granica prema kolegijskom projektu

Ne stoji na frameworku nego na opsegu:

| | kolegijski projekt | ovaj prototip |
|---|---|---|
| radi ono što propis **kaže** | da | ne |
| radi ono što propis **ne kaže** | ne | da, i samo to |

Nijedna datoteka, nijedan redak i nijedna ideja nisu preuzeti iz ranijeg
kolegijskog projekta, i on se u radu ne citira.
