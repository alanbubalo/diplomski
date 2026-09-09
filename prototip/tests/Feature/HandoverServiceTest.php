<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tvrdnje o ishodu, po jedna za svaki scenarij iz poglavlja 7.
 *
 * Svaka je pisana u uvjetnom obliku: uz zadani nacin otkazivanja i zadani
 * obrazac, zavrsno stanje mora biti odredeno. Time testovi nisu samo zastita od
 * regresije nego i izvedba oraclea koji poglavlje 8 opisuje.
 */
final class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function b1_a_missing_response_ends_in_a_named_state(): void
    {
        $handover = $this->service([IntermediaryResponse::TIMEOUT])
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertNotNull($handover);
        $this->assertSame(State::SENT_UNCONFIRMED, $handover->state);
        $this->assertFalse($handover->state->outcomeKnown());
        $this->assertNotNull($handover->impossibility_onset_at, 'Rok mora poceti teci od nastupa.');
        $this->assertNotNull($handover->next_attempt_at, 'Sljedeci pokusaj mora biti zakazan.');
    }

    #[Test]
    public function b1_an_attempt_past_the_deadline_ends_in_deadline_expired(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT]);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        CarbonImmutable::setTestNow($handover->deadline()->expiresAt->addHour());
        $handover = $service->retry($handover->refresh());

        $this->assertSame(State::DEADLINE_EXPIRED, $handover->state);
        $this->assertTrue($handover->state->isFinal());
        $this->assertFalse($handover->state->outcomeKnown(),
            'Istek roka ne otkriva je li raniji zahtjev bio obradjen.');
    }

    #[Test]
    public function e1_without_an_intent_record_no_trace_of_the_handover_remains(): void
    {
        $handover = $this->intermediaryService([IntermediaryResponse::TIMEOUT], withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertNull($handover);
        $this->assertSame(1, Invoice::query()->count(), 'Racun je izdan.');
        $this->assertSame(0, Handover::query()->count(), 'Ali traga o predaji nema.');
    }

    #[Test]
    public function e1_with_an_intent_record_the_system_knows_what_it_meant_to_send(): void
    {
        $this->intermediaryService([IntermediaryResponse::TIMEOUT])
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $unresolved = Handover::query()
            ->where('state', State::SENT_UNCONFIRMED->value)
            ->count();

        $this->assertSame(1, $unresolved);
    }

    #[Test]
    public function b2_s008_on_a_repeated_delivery_of_the_original_ends_as_a_confirmation(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008]);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        CarbonImmutable::setTestNow($handover->next_attempt_at);
        $handover = $service->retry($handover->refresh());

        $this->assertSame(State::CONFIRMED, $handover->state);
    }

    #[Test]
    public function b2_s008_on_a_first_delivery_of_a_correction_ends_as_a_rejection(): void
    {
        $handover = $this->service([IntermediaryResponse::S008])
            ->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        $this->assertSame(State::CORRECTION_REJECTED, $handover->state);
    }

    /**
     * Ponavljanje ne smije obrisati poslovnu namjeru.
     *
     * Ovo je spoj koji je prije bio nevidljiv: ponavljanje je namjeru prepisivalo
     * u RETRY, pa je ponovljena dostava ispravka izgledala kao potvrda ranijeg
     * pokusaja. Sada namjera ostaje ispravak, a tumacenje ostaje dvoznacno.
     */
    #[Test]
    public function b2_a_repeated_delivery_of_a_correction_keeps_the_intent_and_stays_unresolved(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008]);
        $handover = $service->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        $this->assertSame(Intent::CORRECTION, $handover->intent);

        CarbonImmutable::setTestNow($handover->next_attempt_at);
        $handover = $service->retry($handover->refresh());

        $this->assertSame(Intent::CORRECTION, $handover->intent, 'Ponavljanje ne mijenja poslovnu namjeru.');
        $this->assertSame(2, $handover->attempt);
        $this->assertSame(State::SENT_UNCONFIRMED, $handover->state);
        $this->assertFalse($handover->state->outcomeKnown());
    }

        /**
     * Poslovna namjera je izvediva iz dokumenta, pa je zapis za nju ne treba.
     *
     * Ovo je negativan nalaz i namjerno stoji medu tvrdnjama. Indikator kopije
     * je polje eRacuna, pa i izvedba bez zapisa namjere iz spremljenog
     * dokumenta izvodi da je predmet ispravak. Prva dostava ispravka zato u
     * obje izvedbe zavrsi jednako. Kontrast koji bi se ovdje dobio uskracivanjem
     * dokumenta ne bi dokazivao nista o trajnosti zapisa.
     */
    #[Test]
    public function b2_a_first_delivery_of_a_correction_ends_the_same_in_both_implementations(): void
    {
        $withRecord = $this->service([IntermediaryResponse::S008])
            ->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        $withoutRecord = $this->service([IntermediaryResponse::S008], withRecord: false)
            ->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        $this->assertSame(Intent::CORRECTION, $withRecord->intent);
        $this->assertNull($withoutRecord->intent, 'Izvedba bez zapisa namjeru ne biljezi.');
        $this->assertSame(
            Intent::CORRECTION,
            $withoutRecord->invoice->derivedIntent(),
            'Ali je iz dokumenta izvodi.',
        );
        $this->assertSame(State::CORRECTION_REJECTED, $withRecord->state);
        $this->assertSame(State::CORRECTION_REJECTED, $withoutRecord->state);
    }

    /**
     * Ono sto zapis prije predaje doista kupuje: redni pokusaj dostave.
     *
     * Iz dokumenta se ne moze izvesti je li ovo prva ili ponovljena dostava.
     * Ta razlika zivi samo u zapisu predaje, a zapis nastao tek nakon odgovora
     * nakon isteka vremena ne nastaje uopce. Bez njega ponovljena dostava nema
     * na cemu nastaviti, pa se S008 ne moze procitati kao potvrda.
     */
    #[Test]
    public function b2_the_delivery_ordinal_survives_only_in_a_record_written_before_the_call(): void
    {
        $withoutRecord = $this->service(
            [IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008],
            withRecord: false,
        )->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertNull($withoutRecord, 'Nakon isteka vremena nema zapisa o predaji.');
        $this->assertSame(0, Handover::query()->count());

        $service = $this->service([IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008]);
        $withRecord = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertSame(1, $withRecord->attempt);
        $this->assertFalse($withRecord->isRepeatDelivery());

        CarbonImmutable::setTestNow($withRecord->next_attempt_at);
        $withRecord = $service->retry($withRecord->refresh());

        $this->assertTrue($withRecord->isRepeatDelivery(), 'Redni pokusaj je zapisan.');
        $this->assertSame(State::CONFIRMED, $withRecord->state);
    }

    /**
     * Izvedeni kontekst ne razrjesava prvu dostavu izvornika.
     *
     * I sa zapisom i bez njega S008 na prvu dostavu izvornika ostaje dvoznacan,
     * jer identifikator postoji a ovaj ga posiljatelj nije poslao. Zapis tu ne
     * pomaze, i to je granica obrasca.
     */
    #[Test]
    public function b2_a_first_delivery_of_the_original_stays_unresolved_either_way(): void
    {
        $withRecord = $this->service([IntermediaryResponse::S008])
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $withoutRecord = $this->service([IntermediaryResponse::S008], withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertSame(State::SENT_UNCONFIRMED, $withRecord->state);
        $this->assertSame(State::SENT_UNCONFIRMED, $withoutRecord->state);
        $this->assertFalse($withRecord->state->outcomeKnown());
    }

    #[Test]
    public function the_audit_trail_keeps_the_moments_when_the_outcome_was_unknown(): void
    {
        $handover = $this->service([IntermediaryResponse::TIMEOUT])
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $ambiguous = $handover->auditEntries()->where('unambiguous', false)->count();

        $this->assertGreaterThan(0, $ambiguous);
    }

    #[Test]
    public function d1_a_retry_after_the_recovery_event_confirms_within_the_deadline(): void
    {
        $system = new FiscalizationSystem([
            IntermediaryResponse::TIMEOUT,
            IntermediaryResponse::SUCCESS,
        ]);
        $service = $this->serviceWithEndpoint($system);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());

        // Dogadaj oporavka je ono sto poziv prije zakazanog trenutka opravdava.
        $handover = $service->destinationRecovered($handover->refresh());
        $handover = $service->retry($handover);

        $this->assertSame(2, $system->callCount());
        $this->assertSame(State::CONFIRMED, $handover->state);
        $this->assertTrue($handover->deadline()->expiresAt->isFuture());
    }

    /**
     * Raspored koji se ne provodi nije raspored.
     *
     * Bez ovog uvjeta scenarij D1 pokretao je ponavljanje danom nakon kvara, dok
     * je zakazani trenutak bio cetiri dana kasnije, i nista u evidenciji taj
     * raniji poziv nije opravdavalo.
     */
    #[Test]
    public function d1_an_attempt_before_the_scheduled_instant_is_refused(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT]);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('bez dogadaja oporavka');

        $service->retry($handover->refresh());
    }

    #[Test]
    public function d1_the_recovery_event_is_written_into_the_audit_trail(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT]);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        $handover = $service->destinationRecovered($handover->refresh());

        $this->assertSame(
            1,
            $handover->auditEntries()->where('name', 'dogadaj oporavka odredista')->count(),
        );
    }

    #[Test]
    public function d1_without_an_intent_record_cannot_continue_after_the_outage(): void
    {
        $system = new FiscalizationSystem([
            IntermediaryResponse::TIMEOUT,
            IntermediaryResponse::SUCCESS,
        ]);
        $handover = $this->serviceWithEndpoint($system, withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::ORIGINAL);

        $this->assertNull($handover);
        $this->assertSame(1, $system->callCount());
        $this->assertSame(0, Handover::query()->count());
    }

    /** @param list<IntermediaryResponse> $script */
    private function service(array $script, bool $withRecord = true): HandoverService
    {
        return $this->serviceWithEndpoint(new FiscalizationSystem($script), $withRecord);
    }

    /** @param list<IntermediaryResponse> $script */
    private function intermediaryService(array $script, bool $withRecord = true): HandoverService
    {
        return $this->serviceWithEndpoint(new Intermediary($script), $withRecord);
    }

    private function serviceWithEndpoint(
        FiscalizationSystem|Intermediary $endpoint,
        bool $withRecord = true,
    ): HandoverService {
        return new HandoverService(
            $endpoint,
            new StateMachine,
            new ResponseInterpreter,
            new RetrySchedule,
            $withRecord,
        );
    }

    /** @return array<string, mixed> */
    private function invoiceData(bool $copyIndicator = false): array
    {
        return [
            'document_number' => 'R-2026-001',
            'issue_date' => '2026-09-01',
            'document_type' => '380',
            'issuer_oib' => '12345678903',
            'einvoice_type' => 'eRacun',
            'copy_indicator' => $copyIndicator ?: null,
        ];
    }
}
