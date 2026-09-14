<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rok iz cl. 49. st. 1.: pet radnih dana od nastupa nemogucnosti, ne od
 * oporavka. Rok tece i dok sustav ceka, pa raspored pokusaja mora ovisiti o
 * preostalom roku (vidi RetrySchedule).
 *
 * Blagdani se ne racunaju; neradni su samo subota i nedjelja.
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
