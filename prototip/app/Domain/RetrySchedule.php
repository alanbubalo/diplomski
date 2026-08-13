<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dva rasporeda ponavljanja, jedan pokraj drugog, da se razlika vidi.
 *
 * Eksponencijalni odmak razmak POVECAVA kako pokusaji rastu. Rok iz cl. 49.
 * st. 1. istovremeno se SMANJUJE, jer tece od nastupa nemogucnosti. Dva se
 * kretanja razilaze, i to je razlog zbog kojeg uobicajeni obrazac ovdje ne
 * zadovoljava uvjet koji skupina C postavlja.
 *
 * Raspored svjestan roka radi obrnuto: sljedeci pokusaj stavlja na polovicu
 * PREOSTALOG roka, pa se pokusaji zgusnjavaju kako rok tanji. Uz to nikada ne
 * prelazi rok, jer je polovica preostalog uvijek unutar preostalog.
 *
 * Slucajni razmak (Brooker) namjerno je iskljucen po zadanom. Dokazni ispis u
 * poglavlju 7 mora biti ponovljiv, a jedan posiljatelj u prototipu ionako nema
 * s kim se sudariti. U pogonu bi bio ukljucen.
 */
final readonly class RetrySchedule
{
    private const MIN_INTERVAL_S = 60;

    public function __construct(
        private bool $jitter = false,
    ) {}

    /**
     * Sljedeci pokusaj na polovici preostalog roka.
     *
     * Vraca null kada roka vise nema -- tada se ne ponavlja nego se prelazi u
     * DEADLINE_EXPIRED, sto je uvjet nad prijelazom iz StateMachine.
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

    /**
     * Klasicni eksponencijalni odmak, ovdje samo za usporedbu.
     *
     * Ne prima rok jer ga i ne gleda. To je cijela poanta.
     */
    public function exponentialBackoff(int $attempt, CarbonInterface $now): CarbonImmutable
    {
        $interval = self::MIN_INTERVAL_S * (2 ** max(0, $attempt - 1));

        return CarbonImmutable::instance($now)->addSeconds($interval);
    }
}
