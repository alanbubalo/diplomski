<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domena\Automat;
use App\Domena\Namjera;
use App\Domena\Odgovor;
use App\Domena\RasporedPonavljanja;
use App\Domena\Rok;
use App\Domena\Stanje;
use App\Domena\TumacOdgovora;
use App\Models\Predaja;
use App\Models\Racun;
use App\Servisi\Posrednik;
use App\Servisi\Predavatelj;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Pokretac tri scenarija iz poglavlja 7.
 *
 * Sat je fiksiran na jedan trenutak da bi ispis bio ponovljiv. Bez toga
 * dokazni listing u radu ne bi bio provjerljiv, jer bi se mijenjao pri svakom
 * pokretanju. Fiksiranje sata ne dira nijedan nalaz: rok se racuna iz razlike
 * trenutaka, ne iz njihovih apsolutnih vrijednosti.
 */
final class PokreniScenarij extends Command
{
    protected $signature = 'scenarij {oznaka : b1, e1 ili b2}
                            {--bez-zapisa : izvedba koja namjeru zapisuje tek nakon odgovora}';

    protected $description = 'Pokrece jedan scenarij rukovanja greskom na granici C1-C2';

    private const POCETAK = '2026-09-01 09:00:00';

    public function handle(): int
    {
        $oznaka = strtolower((string) $this->argument('oznaka'));
        $sZapisom = ! $this->option('bez-zapisa');

        if (! in_array($oznaka, ['b1', 'e1', 'b2'], true)) {
            $this->error("Nepoznat scenarij: {$oznaka}. Dostupni su b1, e1 i b2.");

            return self::FAILURE;
        }

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::POCETAK));
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->zaglavlje($oznaka, $sZapisom);

        $status = match ($oznaka) {
            'b1' => $this->scenarijB1($sZapisom),
            'e1' => $this->scenarijE1($sZapisom),
            'b2' => $this->scenarijB2($sZapisom),
        };

        CarbonImmutable::setTestNow();

        return $status;
    }

    /**
     * B1: sinkroni poziv istekne bez odgovora.
     *
     * Pokazuje da dokument zavrsi u imenovanom stanju umjesto u rupi, da se
     * raspored ponavljanja racuna iz preostalog roka, i da se u ROK_ISTEKAO
     * ulazi pokusajem koji naide na istrosen rok.
     */
    private function scenarijB1(bool $sZapisom): int
    {
        $predavatelj = $this->predavatelj([Odgovor::ISTEK_VREMENA], $sZapisom);
        $predaja = $predavatelj->izdajIPredaj($this->racun('R-2026-001'), Namjera::PRVO_SLANJE);

        if ($predaja === null) {
            $this->nemaTraga();

            return self::SUCCESS;
        }

        $this->stanjePredaje($predaja);
        $this->usporediRasporede($predaja);

        $this->korak('Rok istekne prije nego potvrda stigne. Pokusaj nailazi na uvjet.');
        CarbonImmutable::setTestNow($predaja->rok()->istice->addHour());

        $predaja = $predavatelj->ponovi($predaja->refresh());
        $this->stanjePredaje($predaja);

        return $this->zakljucak(
            $predaja->stanje,
            'B1 x automat s imenovanim stanjem, B1 x ponavljanje svjesno roka',
        );
    }

    /**
     * E1: predaja bez potvrde na neregulirianoj granici.
     *
     * Pitanje na koje scenarij odgovara nije "je li dokument stigao" nego
     * "moze li poslovni sustav uopce znati da ga je pokusao predati".
     */
    private function scenarijE1(bool $sZapisom): int
    {
        $predavatelj = $this->predavatelj([Odgovor::ISTEK_VREMENA], $sZapisom);
        $predavatelj->izdajIPredaj($this->racun('R-2026-002'), Namjera::PRVO_SLANJE);

        $this->korak('Sustav se pita: koje sam dokumente predao, a nemam potvrdu?');

        $nerazrijesene = Predaja::query()
            ->where('stanje', Stanje::PREDANO_NEPOTVRDENO->value)
            ->count();

        $this->table(
            ['upit', 'odgovor'],
            [
                ['izdanih racuna', (string) Racun::query()->count()],
                ['zapisa u odlaznom pretincu', (string) Predaja::query()->count()],
                ['predano bez potvrde', (string) $nerazrijesene],
            ],
        );

        if ($nerazrijesene === 0) {
            $this->line('  Racun postoji, trag predaje ne postoji. Na pitanje nema odgovora.');
        } else {
            $this->line('  Odgovor postoji i provjerljiv je bez pristupa posredniku.');
        }

        return $this->zakljucak(
            $nerazrijesene > 0 ? Stanje::PREDANO_NEPOTVRDENO : null,
            'E1 x odlazni pretinac i zapis namjere',
        );
    }

    /**
     * B2: ista sifra S008 za dva suprotna ishoda.
     *
     * Prvi dio je ponovni pokusaj nakon isteka vremena, drugi je propisani
     * ispravak pod istim brojem racuna. Posrednik oba puta vraca S008.
     */
    private function scenarijB2(bool $sZapisom): int
    {
        $this->korak('Dio 1: ponovni pokusaj nakon isteka vremena dobiva S008.');

        $prvi = $this->predavatelj([Odgovor::ISTEK_VREMENA, Odgovor::S008], $sZapisom);
        $predaja = $prvi->izdajIPredaj($this->racun('R-2026-003'), Namjera::PRVO_SLANJE);

        if ($predaja === null) {
            // Izvedba bez zapisa ovdje gubi trag, pa ponovni pokusaj nema na
            // sto nastaviti. Scenarij svejedno ide dalje, jer dvoznacnost
            // sifre S008 treba pokazati i bez prvog dijela.
            $this->nemaTraga();
        } else {
            CarbonImmutable::setTestNow(CarbonImmutable::parse(self::POCETAK)->addHours(6));
            $predaja = $prvi->ponovi($predaja->refresh());
            $this->stanjePredaje($predaja);
        }

        $this->korak('Dio 2: propisani ispravak pod istim brojem racuna dobiva istu sifru.');

        $drugi = $this->predavatelj([Odgovor::S008], $sZapisom);
        $ispravak = $drugi->izdajIPredaj(
            $this->racun('R-2026-003', indikatorKopije: true),
            Namjera::ISPRAVAK,
        );

        $this->stanjePredaje($ispravak);

        $this->korak('Ista sifra, dva ishoda.');
        $this->table(
            ['poslano kao', 'odgovor', 'zavrsno stanje', 'ishod poznat'],
            array_values(array_filter([
                $predaja !== null ? [
                    $predaja->namjera->value,
                    $predaja->zadnji_odgovor,
                    $predaja->stanje->value,
                    $predaja->stanje->ishodPoznat() ? 'da' : 'ne',
                ] : null,
                [
                    $ispravak->namjera->value,
                    $ispravak->zadnji_odgovor,
                    $ispravak->stanje->value,
                    $ispravak->stanje->ishodPoznat() ? 'da' : 'ne',
                ],
            ])),
        );

        if (! $sZapisom) {
            $this->line('  Bez zapisane namjere ista sifra vodi u isto stanje, pa se dva');
            $this->line('  suprotna ishoda ne razlikuju. To je slucaj B2.');
        }

        return $this->zakljucak($ispravak->stanje, 'B2 x odlazni pretinac i zapis namjere');
    }

    // ----------------------------------------------------------------
    // pomocno

    private function predavatelj(array $skripta, bool $sZapisom): Predavatelj
    {
        return new Predavatelj(
            new Posrednik($skripta),
            new Automat,
            new TumacOdgovora,
            new RasporedPonavljanja,
            $sZapisom,
        );
    }

    private function racun(string $broj, bool $indikatorKopije = false): array
    {
        return [
            'broj_dokumenta' => $broj,
            'datum_izdavanja' => CarbonImmutable::now()->toDateString(),
            'vrsta_dokumenta' => '380',
            'oib_izdavatelja' => '12345678903',
            'vrsta_eracuna' => 'eRacun',
            'indikator_kopije' => $indikatorKopije ?: null,
        ];
    }

    private function zaglavlje(string $oznaka, bool $sZapisom): void
    {
        $this->newLine();
        $this->line(sprintf(
            '  Scenarij %s | izvedba: %s | sat fiksiran na %s',
            strtoupper($oznaka),
            $sZapisom ? 'sa zapisom namjere' : 'bez zapisa namjere',
            self::POCETAK,
        ));
        $this->line('  '.str_repeat('-', 68));
    }

    private function korak(string $tekst): void
    {
        $this->newLine();
        $this->line("  > {$tekst}");
    }

    private function stanjePredaje(Predaja $predaja): void
    {
        $rok = $predaja->rok();

        $this->newLine();
        $this->table(
            ['polje', 'vrijednost'],
            array_filter([
                ['stanje', $predaja->stanje->value],
                ['opis stanja', $predaja->stanje->opis()],
                ['ishod poznat', $predaja->stanje->ishodPoznat() ? 'da' : 'ne'],
                ['namjera', $predaja->namjera->value],
                ['pokusaj', (string) $predaja->pokusaj],
                ['zadnji odgovor', $predaja->zadnji_odgovor ?? '-'],
                $rok !== null ? ['rok istice', $rok->istice->toDateTimeString()] : null,
                $predaja->sljedeci_pokusaj !== null
                    ? ['sljedeci pokusaj', $predaja->sljedeci_pokusaj->toDateTimeString()]
                    : null,
                ['zakljucak', (string) $predaja->zakljucak],
            ]),
        );
    }

    /** Raspored svjestan roka pokraj eksponencijalnog odmaka, da se razlika vidi. */
    private function usporediRasporede(Predaja $predaja): void
    {
        $rok = $predaja->rok();

        if ($rok === null) {
            return;
        }

        $raspored = new RasporedPonavljanja;
        $redci = [];

        // Dva odvojena sata, jer svaki raspored gradi vlastiti niz trenutaka.
        $satSvjestan = CarbonImmutable::now();
        $satOdmak = CarbonImmutable::now();

        for ($pokusaj = 1; $pokusaj <= 5; $pokusaj++) {
            $svjestan = $raspored->svjestanRoka($rok, $satSvjestan);
            $odmak = $raspored->eksponencijalniOdmak($pokusaj, $satOdmak);

            $redci[] = [
                (string) $pokusaj,
                $svjestan?->toDateTimeString() ?? 'roka nema',
                $odmak->toDateTimeString(),
            ];

            $satSvjestan = $svjestan ?? $satSvjestan;
            $satOdmak = $odmak;
        }

        $this->korak(sprintf('Raspored ponavljanja, rok istice %s.', $rok->istice->toDateTimeString()));
        $this->table(['pokusaj', 'svjestan roka', 'eksponencijalni odmak'], $redci);

        $this->line(sprintf(
            '  Raspored svjestan roka po konstrukciji ostaje unutar roka: svaki pokusaj'
            .PHP_EOL.'  stavlja na polovicu preostalog. Eksponencijalni odmak rok ne gleda, pa'
            .PHP_EOL.'  prvih pet pokusaja potrosi u %s, a rok prekoraci tek na %d. pokusaju.',
            CarbonImmutable::now()->diffForHumans($satOdmak, syntax: true, short: true),
            $this->prviPokusajIzvanRoka($raspored, $rok),
        ));
    }

    /**
     * Na kojem pokusaju eksponencijalni odmak prvi put zakaze slanje izvan roka.
     *
     * Broj nije ukras. On pokazuje da obrazac nema nikakav odnos prema roku:
     * isti raspored uz drukciju osnovicu prekoracuje ranije ili kasnije, a
     * propis se u tom izboru ne pojavljuje.
     */
    private function prviPokusajIzvanRoka(RasporedPonavljanja $raspored, Rok $rok): int
    {
        $sat = CarbonImmutable::now();

        for ($pokusaj = 1; $pokusaj <= 64; $pokusaj++) {
            $sat = $raspored->eksponencijalniOdmak($pokusaj, $sat);

            if ($sat->greaterThan($rok->istice)) {
                return $pokusaj;
            }
        }

        return 0;
    }

    private function nemaTraga(): void
    {
        $this->newLine();
        $this->line('  Odgovor nije stigao, a namjera nije bila zapisana.');
        $this->table(
            ['upit', 'odgovor'],
            [
                ['izdanih racuna', (string) Racun::query()->count()],
                ['zapisa u odlaznom pretincu', (string) Predaja::query()->count()],
            ],
        );
        $this->line('  Poslovni sustav ne moze utvrditi je li dokument predao.');
        $this->newLine();
    }

    private function zakljucak(?Stanje $stanje, string $celije): int
    {
        $this->newLine();
        $this->line('  Zavrsno stanje: '.($stanje?->value ?? 'nema zapisa'));
        $this->line('  Popunjava celije: '.$celije);
        $this->newLine();

        return self::SUCCESS;
    }
}
