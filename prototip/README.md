# Ilustrativni prototip

Prilog diplomskom radu *Obrasci tolerancije na greške u raspodijeljenim
sustavima: studija slučaja Fiskalizacije 2.0*. Razrađen u poglavlju 7.

## Čemu služi

**Prototip postoji da bi tvrdnje u matrici poglavlja 8 prešle iz razine (c)
prosudba autora u razinu (b) pokazano.** Uspoređuje završna stanja osnovne i
predložene izvedbe. Ne mjeri performanse i ne pokazuje sukladnost formata ili
transportnog protokola.

Pokriva granicu C1↔C2 iz odjeljka 6.1 i sinkroni kanal prema Sustavu za
fiskalizaciju. Poslovni sustav mora nakon predaje, nepoznatog ishoda ili
oporavka završiti u stanju koje može obrazložiti.

## Što nema, i zašto

Bez UBL-a, Schematrona, AS4, XAdES-a i PKI-ja, otkrivanja adrese primatelja,
chaos enginea, orkestratora i mjerenja latencije.

Pristupna točka i Sustav za fiskalizaciju kontrolirani su dvojnici. Na naredbu
vraćaju `success`, `timeout` ili `s008`. Stvarna vanjska integracija ne bi
promijenila završna stanja koja scenariji provjeravaju. Predmet je rukovanje
greškom, ne sukladnost.

## Pokretanje

Traži PHP 8.5 (razvijano na 8.5.8) i Composer. Laravel 13.25, SQLite.

```bash
composer setup
```

Četiri scenarija, svaki u dvije izvedbe. Predložena izvedba zapisuje namjeru
prije predaje; osnovna zapis predaje stvara tek nakon odgovora.

Usporedba je namjerno poštena: osnovna izvedba tumači odgovor iz svega što joj
stvarno ostaje, pa poslovnu namjeru izvodi iz spremljenog dokumenta
(`Invoice::derivedIntent()`, indikator kopije je polje e-računa). Iz dokumenta se
ne može izvesti redni pokušaj dostave, i to je ono što zapis prije predaje
doista kupuje.

```bash
php artisan scenario b1                          # istek vremena bez odgovora
php artisan scenario b2                          # ista šifra S008 u tri spoja
php artisan scenario d1                          # nedostupnost i oporavak Sustava
php artisan scenario e1                          # predaja bez potvrde

php artisan scenario b2 --without-intent-record  # ista stvar bez odlaznog pretinca

php artisan state                                # ispis završnog stanja
php artisan test                                 # 32 tvrdnje, 105 provjera
```

Sat je pri scenariju fiksiran na `2026-09-01 09:00:00` da ispis bude ponovljiv.
Isti sat dobiva i dnevnik, pa se baza i `storage/logs/laravel.log` slažu.

Rok od pet radnih dana preskače subotu i nedjelju, ali ne obrađuje blagdane.
Kalendar blagdana nije predmet rada; njegov izostanak mijenja apsolutni datum
isteka, ne odnos rasporeda i roka.

## Jezik

Kod je engleski, komentari i ispisi hrvatski. Ispisi su dokazni materijal za
rad na hrvatskom, pa ostaju u jeziku rada. Vrijednosti enuma su engleske jer
završavaju u bazi. Stanja uz sebe nose i hrvatski opis, pa dokazna tablica
pokazuje jedno i drugo.

| Kod | U radu |
|---|---|
| `sent-unconfirmed` | poslano, nepotvrđeno |
| `correction-rejected` | odbijen ispravak |
| `original` / `correction` | izvornik / ispravak (poslovna namjera) |
| `deadline-expired` | rok istekao, ishod ranijeg slanja ostaje nepoznat |
| `Handover` | predaja |
| `AuditEntry` | evidencija |

## Gdje je što

| Putanja | Sadržaj |
|---|---|
| `app/Domain/State.php` | stanja, uključujući `sent-unconfirmed` koje propis ne imenuje |
| `app/Domain/StateMachine.php` | dopušteni prijelazi; rok kao uvjet nad prijelazom i kao poticaj |
| `app/Domain/Intent.php` | poslovna namjera, odvojena od rednog pokušaja dostave |
| `app/Domain/ResponseInterpreter.php` | jezgra slučaja B2: ista šifra, tri ishoda tumačenja |
| `app/Models/Invoice.php` | `derivedIntent()`: namjera koliko je izvediva iz samog dokumenta |
| `app/Domain/RetrySchedule.php` | raspored vođen preostalim rokom, uz eksponencijalni odmak za usporedbu |
| `app/Domain/Deadline.php` | pet radnih dana od nastupa, čl. 49. st. 1. |
| `app/Services/HandoverService.php` | odlazni pretinac; obje izvedbe |
| `app/Services/FiscalizationSystem.php` | kontrolirani dvojnik za scenarije B1, B2 i D1 |
| `app/Services/Intermediary.php` | kontrolirani dvojnik pristupne točke za E1 |
| `database/migrations/` | tri tablice: `invoices`, `handovers`, `audit_entries` |

## Granica prema kolegijskom projektu

Granica je u opsegu, ne u frameworku.

| | kolegijski projekt | ovaj prototip |
|---|---|---|
| radi ono što propis kaže | da | ne |
| radi ono što propis ne kaže | ne | da, i samo to |

Iz ranijeg kolegijskog projekta nije preuzeta nijedna datoteka, nijedan redak
ni jedna ideja. U radu se ne citira.
