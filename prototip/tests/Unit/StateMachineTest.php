<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Deadline;
use App\Domain\State;
use App\Domain\StateMachine;
use App\Domain\Trigger;
use Carbon\CarbonImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tvrdnje o automatu, pisane u uvjetnom obliku koji metoda iz poglavlja 8 trazi:
 * ako nastupi F, uz obrazac O zavrsno stanje mora biti S.
 */
final class StateMachineTest extends TestCase
{
    private StateMachine $machine;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machine = new StateMachine;
        $this->now = CarbonImmutable::parse('2026-09-01 09:00:00');
    }

    #[Test]
    public function send_without_onset_of_impossibility_skips_the_deadline_guard(): void
    {
        $state = $this->machine->apply(State::PREPARED, Trigger::SENT, null, $this->now);

        $this->assertSame(State::SENT_UNCONFIRMED, $state);
    }

    #[Test]
    public function a_missing_response_does_not_move_the_state(): void
    {
        // Nema poticaja koji bi odgovarao isteku vremena. To je namjerno:
        // dokument koji je poslan i nije potvrden ostaje poslan i nepotvrden.
        $this->assertFalse(
            $this->machine->allows(State::CONFIRMED, Trigger::SENT)
        );
    }

    #[Test]
    public function a_final_state_has_no_outgoing_transition(): void
    {
        $this->expectException(DomainException::class);

        $this->machine->apply(State::CONFIRMED, Trigger::SENT, null, $this->now);
    }

    #[Test]
    public function resending_is_not_allowed_once_the_deadline_expired(): void
    {
        $deadline = Deadline::fromOnset($this->now);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cl. 49. st. 1.');

        $this->machine->apply(
            State::SENT_UNCONFIRMED,
            Trigger::SENT,
            $deadline,
            $deadline->expiresAt->addSecond(),
        );
    }

    #[Test]
    public function deadline_expired_cannot_be_entered_while_the_deadline_still_runs(): void
    {
        $deadline = Deadline::fromOnset($this->now);

        $this->expectException(DomainException::class);

        $this->machine->apply(
            State::SENT_UNCONFIRMED,
            Trigger::DEADLINE_ELAPSED,
            $deadline,
            $this->now->addDay(),
        );
    }

    #[Test]
    public function an_attempt_past_the_deadline_leads_to_deadline_expired(): void
    {
        $deadline = Deadline::fromOnset($this->now);

        $state = $this->machine->apply(
            State::SENT_UNCONFIRMED,
            Trigger::DEADLINE_ELAPSED,
            $deadline,
            $deadline->expiresAt->addSecond(),
        );

        $this->assertSame(State::DEADLINE_EXPIRED, $state);
        $this->assertTrue($state->isFinal());
    }
}
