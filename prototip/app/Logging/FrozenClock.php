<?php

declare(strict_types=1);

namespace App\Logging;

use Carbon\CarbonImmutable;
use Monolog\LogRecord;

/**
 * Monolog obradivac koji zapisu daje isti sat kojim se sluzi ostatak sustava.
 *
 * Bez njega scenarij proizvodi dva dokazna materijala koji se ne slazu: baza
 * biljezi zamrznuti trenutak scenarija, a dnevnik stvarno vrijeme pokretanja.
 * Nesklad nije kvar, ali je smetnja pri citanju, jer isti dogadaj u dva ispisa
 * nosi dva vremena.
 *
 * Kada sat nije zamrznut, CarbonImmutable::now() vraca stvarno vrijeme, pa se u
 * pogonu nista ne mijenja.
 */
final class FrozenClock
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(datetime: CarbonImmutable::now()->toDateTimeImmutable());
    }
}
