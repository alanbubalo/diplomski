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
            ->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

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
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        CarbonImmutable::setTestNow($handover->deadline()->expiresAt->addHour());
        $handover = $service->retry($handover->refresh());

        $this->assertSame(State::DEADLINE_EXPIRED, $handover->state);
        $this->assertTrue($handover->state->isFinal());
    }

    #[Test]
    public function e1_without_an_intent_record_no_trace_of_the_handover_remains(): void
    {
        $handover = $this->intermediaryService([IntermediaryResponse::TIMEOUT], withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        $this->assertNull($handover);
        $this->assertSame(1, Invoice::query()->count(), 'Racun je izdan.');
        $this->assertSame(0, Handover::query()->count(), 'Ali traga o predaji nema.');
    }

    #[Test]
    public function e1_with_an_intent_record_the_system_knows_what_it_meant_to_send(): void
    {
        $this->intermediaryService([IntermediaryResponse::TIMEOUT])
            ->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        $unresolved = Handover::query()
            ->where('state', State::SENT_UNCONFIRMED->value)
            ->count();

        $this->assertSame(1, $unresolved);
    }

    #[Test]
    public function b2_s008_after_a_retry_ends_as_a_confirmation(): void
    {
        $service = $this->service([IntermediaryResponse::TIMEOUT, IntermediaryResponse::S008]);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(6));
        $handover = $service->retry($handover->refresh());

        $this->assertSame(State::CONFIRMED, $handover->state);
    }

    #[Test]
    public function b2_s008_after_a_correction_ends_as_a_rejection(): void
    {
        $handover = $this->service([IntermediaryResponse::S008])
            ->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        $this->assertSame(State::CORRECTION_REJECTED, $handover->state);
    }

    #[Test]
    public function b2_without_an_intent_record_one_code_cannot_separate_two_outcomes(): void
    {
        $retry = $this->service([IntermediaryResponse::S008], withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::RETRY);

        $correction = $this->service([IntermediaryResponse::S008], withRecord: false)
            ->issueAndHandOver($this->invoiceData(copyIndicator: true), Intent::CORRECTION);

        // Dvije suprotne namjere, ista sifra, isto zavrsno stanje. To je B2.
        $this->assertSame($retry->state, $correction->state);
        $this->assertSame(State::SENT_UNCONFIRMED, $retry->state);
        $this->assertFalse($retry->state->outcomeKnown());
    }

    #[Test]
    public function the_audit_trail_keeps_the_moments_when_the_outcome_was_unknown(): void
    {
        $handover = $this->service([IntermediaryResponse::TIMEOUT])
            ->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        $ambiguous = $handover->auditEntries()->where('unambiguous', false)->count();

        $this->assertGreaterThan(0, $ambiguous);
    }

    #[Test]
    public function d1_a_retry_after_recovery_confirms_within_the_deadline(): void
    {
        $system = new FiscalizationSystem([
            IntermediaryResponse::TIMEOUT,
            IntermediaryResponse::SUCCESS,
        ]);
        $service = $this->serviceWithEndpoint($system);
        $handover = $service->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        $handover = $service->retry($handover->refresh());

        $this->assertSame(2, $system->callCount());
        $this->assertSame(State::CONFIRMED, $handover->state);
        $this->assertTrue($handover->deadline()->expiresAt->isFuture());
    }

    #[Test]
    public function d1_without_an_intent_record_cannot_continue_after_the_outage(): void
    {
        $system = new FiscalizationSystem([
            IntermediaryResponse::TIMEOUT,
            IntermediaryResponse::SUCCESS,
        ]);
        $handover = $this->serviceWithEndpoint($system, withRecord: false)
            ->issueAndHandOver($this->invoiceData(), Intent::FIRST_SEND);

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
