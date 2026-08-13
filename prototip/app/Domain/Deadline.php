<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rok iz cl. 49. st. 1.: pet radnih dana od dana kada je nastupila nemogucnost,
 * a NE od oporavka.
 *
 * Ta jedna rijec je razlog zbog kojeg obicni eksponencijalni odmak ovdje ne
 * valja. Rok se ne zaustavlja dok sustav ceka, pa raspored pokusaja mora
 * ovisiti o preostalom roku (vidi RetrySchedule).
 *
 * OPSEG: blagdani se ne racunaju. Prototip broji samo subotu i nedjelju kao
 * neradne. Kalendar blagdana nije predmet rada i njegov izostanak ne mijenja
 * nijedan nalaz -- mijenja samo apsolutni datum isteka.
 */
final readonly class Deadline
{
    private const WORKING_DAYS = 5;

    private function __construct(
        public CarbonImmutable $onset,
        public CarbonImmutable $expiresAt,
    ) {}

    /** Rok pocinje teci od DANA nastupa nemogucnosti, pa se sat odbacuje. */
    public static function fromOnset(CarbonInterface $onset): self
    {
        $day = CarbonImmutable::instance($onset)->startOfDay();

        return new self($day, $day->addWeekdays(self::WORKING_DAYS)->endOfDay());
    }

    public function remainingSeconds(CarbonInterface $now): int
    {
        return (int) max(0, $now->diffInSeconds($this->expiresAt, false));
    }

    public function hasExpired(CarbonInterface $now): bool
    {
        return $this->remainingSeconds($now) <= 0;
    }
}
