<?php

declare(strict_types=1);

namespace App\Domena;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rok iz cl. 49. st. 1.: pet radnih dana od dana kada je nastupila
 * nemogucnost, a NE od oporavka.
 *
 * Ta jedna rijec je razlog zbog kojeg obicni eksponencijalni odmak ovdje ne
 * valja. Rok se ne zaustavlja dok sustav ceka, pa raspored pokusaja mora
 * ovisiti o preostalom roku (vidi RasporedPonavljanja).
 *
 * OPSEG: blagdani se ne racunaju. Prototip broji samo subotu i nedjelju kao
 * neradne. Kalendar blagdana nije predmet rada i njegov izostanak ne mijenja
 * nijedan nalaz -- mijenja samo apsolutni datum isteka.
 */
final readonly class Rok
{
    private const RADNIH_DANA = 5;

    private function __construct(
        public CarbonImmutable $nastanakNemogucnosti,
        public CarbonImmutable $istice,
    ) {}

    /** Rok pocinje teci od DANA nastupa nemogucnosti, pa se sat odbacuje. */
    public static function odNastupa(CarbonInterface $nastanak): self
    {
        $dan = CarbonImmutable::instance($nastanak)->startOfDay();

        return new self($dan, $dan->addWeekdays(self::RADNIH_DANA)->endOfDay());
    }

    public function preostaloSekundi(CarbonInterface $sada): int
    {
        return (int) max(0, $sada->diffInSeconds($this->istice, false));
    }

    public function istrosen(CarbonInterface $sada): bool
    {
        return $this->preostaloSekundi($sada) <= 0;
    }
}
