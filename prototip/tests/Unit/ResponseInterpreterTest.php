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
 * Jezgra slucaja B2: ista sifra, tri ishoda tumacenja. Sifru tumaci spoj
 * poslovne namjere i rednog pokusaja dostave, a zapis namjere dvoznacnost
 * suzava, ali je ne uklanja.
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
    public function s008_on_a_repeated_delivery_of_the_original_means_confirmation(): void
    {
        $interpretation = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::ORIGINAL,
            repeatDelivery: true,
        );

        $this->assertTrue($interpretation->unambiguous);
        $this->assertSame(Trigger::CONFIRMATION_FROM_S008, $interpretation->trigger);
    }

    #[Test]
    public function s008_on_a_first_delivery_of_a_correction_means_rejection(): void
    {
        $interpretation = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::CORRECTION,
            repeatDelivery: false,
        );

        $this->assertTrue($interpretation->unambiguous);
        $this->assertSame(Trigger::REJECTION_FROM_S008, $interpretation->trigger);
    }

    #[Test]
    public function the_same_code_under_two_intents_yields_two_opposite_triggers(): void
    {
        $original = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::ORIGINAL,
            repeatDelivery: true,
        );
        $correction = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::CORRECTION,
            repeatDelivery: false,
        );

        $this->assertNotSame($original->trigger, $correction->trigger);
    }

    /**
     * Spoj koji zapis namjere ne razrjesava: kod ponovljene dostave ispravka
     * S008 se moze odnositi na izvornik ili na vlastiti raniji pokusaj.
     */
    #[Test]
    public function s008_on_a_repeated_delivery_of_a_correction_stays_ambiguous(): void
    {
        $interpretation = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::CORRECTION,
            repeatDelivery: true,
        );

        $this->assertFalse($interpretation->unambiguous);
        $this->assertNull($interpretation->trigger);
    }

    #[Test]
    public function s008_on_a_first_delivery_of_the_original_stays_ambiguous(): void
    {
        $interpretation = $this->interpreter->interpret(
            IntermediaryResponse::S008,
            Intent::ORIGINAL,
            repeatDelivery: false,
        );

        $this->assertFalse($interpretation->unambiguous);
        $this->assertNull($interpretation->trigger);
    }

    #[Test]
    public function s008_without_a_recorded_intent_stays_ambiguous(): void
    {
        foreach ([false, true] as $repeat) {
            $interpretation = $this->interpreter->interpret(
                IntermediaryResponse::S008,
                null,
                repeatDelivery: $repeat,
            );

            $this->assertFalse($interpretation->unambiguous);
            $this->assertNull($interpretation->trigger);
        }
    }

    #[Test]
    public function a_missing_response_is_never_unambiguous(): void
    {
        foreach ([Intent::ORIGINAL, Intent::CORRECTION, null] as $intent) {
            foreach ([false, true] as $repeat) {
                $interpretation = $this->interpreter->interpret(
                    IntermediaryResponse::TIMEOUT,
                    $intent,
                    repeatDelivery: $repeat,
                );

                $this->assertFalse($interpretation->unambiguous);
            }
        }
    }
}
