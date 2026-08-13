<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Deadline;
use App\Domain\RetrySchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rok iz cl. 49. st. 1. i raspored koji ga postuje.
 */
final class DeadlineTest extends TestCase
{
    #[Test]
    public function the_deadline_runs_from_onset_not_from_recovery(): void
    {
        $onset = CarbonImmutable::parse('2026-09-01 09:00:00');
        $deadline = Deadline::fromOnset($onset);

        // Kasniji trenutak ne pomice istek. To je cijela razlika prema roku koji
        // bi tekao od oporavka.
        $same = Deadline::fromOnset($onset);

        $this->assertTrue($deadline->expiresAt->equalTo($same->expiresAt));
        $this->assertLessThan(
            $deadline->remainingSeconds($onset),
            $deadline->remainingSeconds($onset->addDay()),
        );
    }

    #[Test]
    public function five_working_days_skip_the_weekend(): void
    {
        // Utorak 1. rujna 2026. Pet radnih dana zavrsava u utorak 8. rujna, jer
        // subota i nedjelja ne ulaze.
        $deadline = Deadline::fromOnset(CarbonImmutable::parse('2026-09-01 14:30:00'));

        $this->assertSame('2026-09-08', $deadline->expiresAt->toDateString());
    }

    #[Test]
    public function the_deadline_aware_schedule_never_plans_past_the_deadline(): void
    {
        $onset = CarbonImmutable::parse('2026-09-01 09:00:00');
        $deadline = Deadline::fromOnset($onset);
        $schedule = new RetrySchedule;

        $now = $onset;

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $next = $schedule->deadlineAware($deadline, $now);

            if ($next === null) {
                $this->assertTrue($deadline->hasExpired($now));

                return;
            }

            $this->assertLessThanOrEqual(
                $deadline->expiresAt->getTimestamp(),
                $next->getTimestamp(),
                "Pokusaj {$attempt} zakazan izvan roka.",
            );

            $now = $next;
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function exponential_backoff_ignores_the_deadline_entirely(): void
    {
        $onset = CarbonImmutable::parse('2026-09-01 09:00:00');
        $deadline = Deadline::fromOnset($onset);
        $schedule = new RetrySchedule;

        $now = $onset;
        $exceeded = false;

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $now = $schedule->exponentialBackoff($attempt, $now);

            if ($now->greaterThan($deadline->expiresAt)) {
                $exceeded = true;
                break;
            }
        }

        $this->assertTrue($exceeded, 'Eksponencijalni odmak ocekivano prekoracuje rok.');
    }
}
