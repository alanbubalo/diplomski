<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Intent;
use App\Domain\IntermediaryResponse;
use App\Domain\ResponseInterpreter;
use App\Domain\Trigger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Jezgra slucaja B2: ista sifra, dva znacenja, razlucena samo zapisanom
 * namjerom.
 */
final class ResponseInterpreterTest extends TestCase
{
    private ResponseInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpreter = new ResponseInterpreter;
    }

    #[Test]
    public function s008_with_a_recorded_retry_means_confirmation(): void
    {
        $interpretation = $this->interpreter->interpret(IntermediaryResponse::S008, Intent::RETRY);

        $this->assertTrue($interpretation->unambiguous);
        $this->assertSame(Trigger::CONFIRMATION_FROM_S008, $interpretation->trigger);
    }

    #[Test]
    public function s008_with_a_recorded_correction_means_rejection(): void
    {
        $interpretation = $this->interpreter->interpret(IntermediaryResponse::S008, Intent::CORRECTION);

        $this->assertTrue($interpretation->unambiguous);
        $this->assertSame(Trigger::REJECTION_FROM_S008, $interpretation->trigger);
    }

    #[Test]
    public function the_same_code_under_two_intents_yields_two_opposite_triggers(): void
    {
        $retry = $this->interpreter->interpret(IntermediaryResponse::S008, Intent::RETRY);
        $correction = $this->interpreter->interpret(IntermediaryResponse::S008, Intent::CORRECTION);

        $this->assertNotSame($retry->trigger, $correction->trigger);
    }

    #[Test]
    public function s008_without_a_recorded_intent_stays_ambiguous(): void
    {
        $interpretation = $this->interpreter->interpret(IntermediaryResponse::S008, null);

        $this->assertFalse($interpretation->unambiguous);
        $this->assertNull($interpretation->trigger);
    }

    #[Test]
    public function a_missing_response_is_never_unambiguous(): void
    {
        foreach ([Intent::FIRST_SEND, Intent::RETRY, Intent::CORRECTION, null] as $intent) {
            $interpretation = $this->interpreter->interpret(IntermediaryResponse::TIMEOUT, $intent);

            $this->assertFalse($interpretation->unambiguous);
        }
    }
}
