<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Deadline;
use App\Domain\Intent;
use App\Domain\IntermediaryResponse;
use App\Domain\ResponseInterpreter;
use App\Domain\RetrySchedule;
use App\Domain\State;
use App\Domain\StateMachine;
use App\Models\Handover;
use App\Models\Invoice;
use App\Services\FiscalizationSystem;
use App\Services\HandoverService;
use App\Services\Intermediary;
use App\Services\SubmissionEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Pokretac cetiri scenarija iz poglavlja 7.
 *
 * Sat je fiksiran na jedan trenutak da bi ispis bio ponovljiv. Bez toga dokazni
 * listing u radu ne bi bio provjerljiv, jer bi se mijenjao pri svakom
 * pokretanju. Fiksiranje sata ne dira nijedan nalaz: rok se racuna iz razlike
 * trenutaka, ne iz njihovih apsolutnih vrijednosti.
 *
 * Ispisi su hrvatski jer su dokazni materijal za rad na hrvatskom. Kod je
 * engleski.
 */
final class RunScenario extends Command
{
    protected $signature = 'scenario {code : b1, b2, d1 ili e1}
                            {--without-intent-record : izvedba koja namjeru zapisuje tek nakon odgovora}';

    protected $description = 'Pokrece scenarij predaje ili fiskalizacije eRacuna';

    private const CLOCK_START = '2026-09-01 09:00:00';

    public function handle(): int
    {
        $code = strtolower((string) $this->argument('code'));
        $withRecord = ! $this->option('without-intent-record');

        if (! in_array($code, ['b1', 'b2', 'd1', 'e1'], true)) {
            $this->error("Nepoznat scenarij: {$code}. Dostupni su b1, b2, d1 i e1.");

            return self::FAILURE;
        }

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::CLOCK_START));
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->header($code, $withRecord);

        $status = match ($code) {
            'b1' => $this->scenarioB1($withRecord),
            'b2' => $this->scenarioB2($withRecord),
            'd1' => $this->scenarioD1($withRecord),
            'e1' => $this->scenarioE1($withRecord),
        };

        CarbonImmutable::setTestNow();

        return $status;
    }

    /**
     * B1: sinkroni poziv istekne bez odgovora.
     *
     * Pokazuje da dokument zavrsi u imenovanom stanju umjesto u rupi, da se
     * raspored ponavljanja racuna iz preostalog roka, i da se u DEADLINE_EXPIRED
     * ulazi pokusajem koji naide na istrosen rok.
     */
    private function scenarioB1(bool $withRecord): int
    {
        $service = $this->fiscalizationService([IntermediaryResponse::TIMEOUT], $withRecord);
        $handover = $service->issueAndHandOver($this->invoiceData('R-2026-001'), Intent::ORIGINAL);

        if ($handover === null) {
            $this->noTrace();

            return self::SUCCESS;
        }

        $this->dumpHandover($handover);
        $this->compareSchedules($handover);

        $this->step('Rok istekne prije nego potvrda stigne. Pokusaj nailazi na uvjet.');
        CarbonImmutable::setTestNow($handover->deadline()->expiresAt->addHour());

        $handover = $service->retry($handover->refresh());
        $this->dumpHandover($handover);

        return $this->summary(
            $handover->state,
            'B1 x automat s imenovanim stanjem, B1 x ponavljanje svjesno roka',
        );
    }

    /**
     * E1: predaja bez potvrde na nereguliranoj granici.
     *
     * Pitanje na koje scenarij odgovara nije "je li dokument stigao" nego "moze
     * li poslovni sustav uopce znati da ga je pokusao predati".
     */
    private function scenarioE1(bool $withRecord): int
    {
        $service = $this->intermediaryService([IntermediaryResponse::TIMEOUT], $withRecord);
        $service->issueAndHandOver($this->invoiceData('R-2026-002'), Intent::ORIGINAL);

        $this->step('Sustav se pita: koje sam dokumente predao, a nemam potvrdu?');

        $unresolved = Handover::query()
            ->where('state', State::SENT_UNCONFIRMED->value)
            ->count();

        $this->table(
            ['upit', 'odgovor'],
            [
                ['izdanih racuna', (string) Invoice::query()->count()],
                ['zapisa u odlaznom pretincu', (string) Handover::query()->count()],
                ['predano bez potvrde', (string) $unresolved],
            ],
        );

        if ($unresolved === 0) {
            $this->line('  Racun postoji, trag predaje ne postoji. Na pitanje nema odgovora.');
        } else {
            $this->line('  Odgovor postoji i provjerljiv je bez pristupa posredniku.');
        }

        return $this->summary(
            $unresolved > 0 ? State::SENT_UNCONFIRMED : null,
            'E1 x odlazni pretinac i zapis namjere',
        );
    }

        /**
     * B2: ista sifra S008 u tri spoja namjere i rednog pokusaja.
     *
     * ⚠ USPOREDBA JE POSTENA: izvedba bez zapisa namjere iz spremljenog
     * dokumenta izvodi je li predmet ispravak (indikator kopije je polje
     * eRacuna). Zato dio 2 u OBJE izvedbe zavrsi jednako, i to je nalaz, a ne
     * propust scenarija. Kontrast se pojavljuje tek u dijelovima 1 i 3, gdje
     * treba redni pokusaj dostave, a njega dokument ne nosi.
     */
    private function scenarioB2(bool $withRecord): int
    {
                $this->step('Dio 1: ponovljena dostava izvornika nakon isteka vremena dobiva S008.');

        $first = $this->fiscalizationService(
            [IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008],
            $withRecord,
        );
        $handover = $first->issueAndHandOver($this->invoiceData('R-2026-003'), Intent::ORIGINAL);

        if ($handover === null) {
            // Izvedba bez zapisa ovdje gubi trag, pa ponovni pokusaj nema na sto
            // nastaviti. Scenarij svejedno ide dalje, jer dvoznacnost sifre S008
            // treba pokazati i bez prvog dijela.
            $this->noTrace();
        } else {
            // Pokusaj se pokrece na trenutak koji je raspored zakazao, ne na
            // proizvoljan. Sustav koji raspored izracuna i ne drzi ga se nema
            // sto pokazati.
            CarbonImmutable::setTestNow($handover->next_attempt_at);
            $handover = $first->retry($handover->refresh());
            $this->dumpHandover($handover);
        }

        $this->step('Dio 2: propisani ispravak pod istim brojem racuna dobiva istu sifru.');

        // Svaki dio pocinje od istog trenutka, jer inace nosi rok koji je
        // pomaknuo prethodni dio i ispis se vise ne cita kao jedna slika.
        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::CLOCK_START));

        $second = $this->fiscalizationService([IntermediaryResponse::S008], $withRecord);
        $correction = $second->issueAndHandOver(
            $this->invoiceData('R-2026-003', copyIndicator: true),
            Intent::CORRECTION,
        );

        $this->dumpHandover($correction);

                $this->step('Dio 2: ista sifra, ishod jednak u obje izvedbe.');
        $this->table(
            ['namjera i dostava', 'odgovor', 'zavrsno stanje', 'ishod poznat'],
            array_values(array_filter([
                $handover !== null ? [
                    $this->intentLabel($handover),
                    $handover->last_response,
                    $handover->state->value,
                    $handover->state->outcomeKnown() ? 'da' : 'ne',
                ] : null,
                [
                    $this->intentLabel($correction),
                    $correction->last_response,
                    $correction->state->value,
                    $correction->state->outcomeKnown() ? 'da' : 'ne',
                ],
            ])),
        );

                        $this->line(sprintf(
            '  Zapisana namjera: %s. Iz dokumenta izvedena namjera: %s.',
            $correction->intent?->value ?? 'nema je',
            $correction->invoice->derivedIntent()->value,
        ));
        $this->line('  Prva dostava ispravka zato u obje izvedbe zavrsi jednako:');
        $this->line('  poslovnu namjeru nosi sam dokument, pa za nju zapis nije potreban.');

                $this->step('Dio 3: ponovljena dostava ispravka. Ni zapis je ne razrjesava.');

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::CLOCK_START));

        $third = $this->fiscalizationService(
            [IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008],
            $withRecord,
        );
        $repeated = $third->issueAndHandOver(
            $this->invoiceData('R-2026-005', copyIndicator: true),
            Intent::CORRECTION,
        );

                if ($repeated === null) {
            $this->noTrace();
            $this->line('  Bez zapisa nema ni rednog pokusaja dostave, pa se prva i');
            $this->line('  ponovljena dostava ne mogu razlikovati.');
        } else {
            CarbonImmutable::setTestNow($repeated->next_attempt_at);
            $repeated = $third->retry($repeated->refresh());
            $this->dumpHandover($repeated);
            $this->line('  Sifra se moze odnositi na izvornik ili na vlastiti raniji pokusaj.');
            $this->line('  Zapis rednog pokusaja dvoznacnost suzava, ne uklanja je.');
        }

                return $this->summary($correction->state, 'B2 x odlazni pretinac i zapis rednog pokusaja');
    }

    /** D1: Sustav za fiskalizaciju ne odgovori, pa se oporavi unutar roka. */
    private function scenarioD1(bool $withRecord): int
    {
        $system = new FiscalizationSystem([
            IntermediaryResponse::TIMEOUT,
            IntermediaryResponse::SUCCESS,
        ]);
        $service = $this->serviceFor($system, $withRecord);

        $this->step('Sustav za fiskalizaciju ne odgovara na prvi poziv.');
        $handover = $service->issueAndHandOver(
            $this->invoiceData('R-2026-004'),
            Intent::ORIGINAL,
        );

        if ($handover === null) {
            $this->noTrace();

            return $this->summary(null, 'D1 bez zapisa namjere ostaje bez oporavka');
        }

        $this->dumpHandover($handover);

        $this->step('Odrediste se oporavlja sljedeci dan, prije isteka roka.');
        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::CLOCK_START)->addDay());

        // Raspored je sljedeci pokusaj zakazao za 5. rujna, jer racuna s tim da
        // se o odredistu nista novo ne zna. Dogadaj oporavka je ono sto raniji
        // poziv opravdava, pa se biljezi prije njega.
        $handover = $service->destinationRecovered($handover->refresh());
        $handover = $service->retry($handover);
        $this->dumpHandover($handover);

        $this->table(
            ['poziva prema Sustavu', 'zavrsno stanje', 'unutar roka'],
            [['2', $handover->state->value, 'da']],
        );

        return $this->summary(
            $handover->state,
            'D1 x ponavljanje svjesno roka, odlazni pretinac i imenovano stanje',
        );
    }

    // ----------------------------------------------------------------
    // pomocno

    /** @param list<IntermediaryResponse> $script */
    private function fiscalizationService(array $script, bool $withRecord): HandoverService
    {
        return $this->serviceFor(new FiscalizationSystem($script), $withRecord);
    }

    /** @param list<IntermediaryResponse> $script */
    private function intermediaryService(array $script, bool $withRecord): HandoverService
    {
        return $this->serviceFor(new Intermediary($script), $withRecord);
    }

    private function serviceFor(SubmissionEndpoint $endpoint, bool $withRecord): HandoverService
    {
        return new HandoverService(
            $endpoint,
            new StateMachine,
            new ResponseInterpreter,
            new RetrySchedule,
            $withRecord,
        );
    }

    /** @return array<string, mixed> */
    private function invoiceData(string $number, bool $copyIndicator = false): array
    {
        return [
            'document_number' => $number,
            'issue_date' => CarbonImmutable::now()->toDateString(),
            'document_type' => '380',
            'issuer_oib' => '12345678903',
            'einvoice_type' => 'eRacun',
            'copy_indicator' => $copyIndicator ?: null,
        ];
    }

    /**
     * Namjera i redni pokusaj zajedno, jer sifru tumaci njihov spoj.
     *
     * Prazna namjera nije ukras ispisa. Izvedba koja predaju zapisuje tek nakon
     * odgovora nema sto upisati, pa se to i vidi.
     */
    private function intentLabel(Handover $handover): string
    {
        return sprintf(
            '%s / %s dostava',
            $handover->intent?->value ?? 'namjera nije zapisana',
            $handover->isRepeatDelivery() ? 'ponovljena' : 'prva',
        );
    }

    private function header(string $code, bool $withRecord): void
    {
        $this->newLine();
        $this->line(sprintf(
            '  Scenarij %s | izvedba: %s | sat fiksiran na %s',
            strtoupper($code),
            $withRecord ? 'sa zapisom namjere' : 'bez zapisa namjere',
            self::CLOCK_START,
        ));
        $this->line('  '.str_repeat('-', 68));
    }

    private function step(string $text): void
    {
        $this->newLine();
        $this->line("  > {$text}");
    }

    private function dumpHandover(Handover $handover): void
    {
        $deadline = $handover->deadline();

        $this->newLine();
        $this->table(
            ['polje', 'vrijednost'],
            array_filter([
                ['stanje', $handover->state->value],
                ['opis stanja', $handover->state->description()],
                ['ishod poznat', $handover->state->outcomeKnown() ? 'da' : 'ne'],
                ['namjera', $handover->intent?->value ?? 'nije zapisana'],
                ['pokusaj', (string) $handover->attempt],
                ['dostava', $handover->isRepeatDelivery() ? 'ponovljena' : 'prva'],
                ['zadnji odgovor', $handover->last_response ?? '-'],
                $deadline !== null ? ['rok istice', $deadline->expiresAt->toDateTimeString()] : null,
                $handover->next_attempt_at !== null
                    ? ['sljedeci pokusaj', $handover->next_attempt_at->toDateTimeString()]
                    : null,
                ['zakljucak', (string) $handover->conclusion],
            ]),
        );
    }

    /** Raspored svjestan roka pokraj eksponencijalnog odmaka, da se razlika vidi. */
    private function compareSchedules(Handover $handover): void
    {
        $deadline = $handover->deadline();

        if ($deadline === null) {
            return;
        }

        $schedule = new RetrySchedule;
        $rows = [];

        // Dva odvojena sata, jer svaki raspored gradi vlastiti niz trenutaka.
        $clockAware = CarbonImmutable::now();
        $clockBackoff = CarbonImmutable::now();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $aware = $schedule->deadlineAware($deadline, $clockAware);
            $backoff = $schedule->exponentialBackoff($attempt, $clockBackoff);

            $rows[] = [
                (string) $attempt,
                $aware?->toDateTimeString() ?? 'roka nema',
                $backoff->toDateTimeString(),
            ];

            $clockAware = $aware ?? $clockAware;
            $clockBackoff = $backoff;
        }

        $this->step(sprintf('Raspored ponavljanja, rok istice %s.', $deadline->expiresAt->toDateTimeString()));
        $this->table(['pokusaj', 'svjestan roka', 'eksponencijalni odmak'], $rows);

        $this->line(sprintf(
            '  Raspored svjestan roka po konstrukciji ostaje unutar roka: svaki pokusaj'
            .PHP_EOL.'  stavlja na polovicu preostalog. Eksponencijalni odmak rok ne gleda, pa'
            .PHP_EOL.'  prvih pet pokusaja potrosi u %s, a rok prekoraci tek na %d. pokusaju.',
            CarbonImmutable::now()->diffForHumans($clockBackoff, syntax: true, short: true),
            $this->firstAttemptPastDeadline($schedule, $deadline),
        ));
    }

    /**
     * Na kojem pokusaju eksponencijalni odmak prvi put zakaze slanje izvan roka.
     *
     * Broj nije ukras. On pokazuje da obrazac nema nikakav odnos prema roku: isti
     * raspored uz drukciju osnovicu prekoracuje ranije ili kasnije, a propis se u
     * tom izboru ne pojavljuje.
     */
    private function firstAttemptPastDeadline(RetrySchedule $schedule, Deadline $deadline): int
    {
        $clock = CarbonImmutable::now();

        for ($attempt = 1; $attempt <= 64; $attempt++) {
            $clock = $schedule->exponentialBackoff($attempt, $clock);

            if ($clock->greaterThan($deadline->expiresAt)) {
                return $attempt;
            }
        }

        return 0;
    }

    private function noTrace(): void
    {
        $this->newLine();
        $this->line('  Odgovor nije stigao, a namjera nije bila zapisana.');
        $this->table(
            ['upit', 'odgovor'],
            [
                ['izdanih racuna', (string) Invoice::query()->count()],
                ['zapisa u odlaznom pretincu', (string) Handover::query()->count()],
            ],
        );
        $this->line('  Poslovni sustav ne moze utvrditi je li dokument predao.');
        $this->newLine();
    }

    private function summary(?State $state, string $cells): int
    {
        $this->newLine();
        $this->line('  Zavrsno stanje: '.($state?->value ?? 'nema zapisa'));
        $this->line('  Popunjava celije: '.$cells);
        $this->newLine();

        return self::SUCCESS;
    }
}
