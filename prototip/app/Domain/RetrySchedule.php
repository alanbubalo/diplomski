<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dva rasporeda ponavljanja, jedan pokraj drugog, da se razlika vidi.
 *
 * Eksponencijalni odmak razmak povecava, a rok iz cl. 49. st. 1. se istovremeno
 * smanjuje. Raspored svjestan roka radi obrnuto: sljedeci pokusaj stavlja na
 * polovicu preostalog roka, pa se pokusaji zgusnjavaju i nikada ne prelaze rok.
 *
 * Slucajni razmak iskljucen je po zadanom da dokazni ispis ostane ponovljiv.
 */
final readonly class RetrySchedule
{
    private const MIN_INTERVAL_S = 60;

    public function __construct(
        private bool $jitter = false,
    ) {}

    /**
     * Sljedeci pokusaj na polovici preostalog roka. Vraca null kada roka vise
     * nema; tada se ne ponavlja nego se prelazi u DEADLINE_EXPIRED.
     */
    public function deadlineAware(Deadline $deadline, CarbonInterface $now): ?CarbonImmutable
    {
        $remaining = $deadline->remainingSeconds($now);

        if ($remaining <= 0) {
            return null;
        }

        $interval = max(self::MIN_INTERVAL_S, intdiv($remaining, 2));

        if ($this->jitter) {
            $interval = random_int(intdiv($interval, 2), $interval);
        }

        return CarbonImmutable::instance($now)->addSeconds(min($interval, $remaining));
    }

    /** Klasicni eksponencijalni odmak, za usporedbu. Rok ne prima jer ga i ne gleda. */
    public function exponentialBackoff(int $attempt, CarbonInterface $now): CarbonImmutable
    {
        $interval = self::MIN_INTERVAL_S * (2 ** max(0, $attempt - 1));

        return CarbonImmutable::instance($now)->addSeconds($interval);
    }
}
