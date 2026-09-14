<?php

declare(strict_types=1);

namespace App\Logging;

use Carbon\CarbonImmutable;
use Monolog\LogRecord;

/**
 * Monolog obradivac koji zapisu daje isti sat kojim se sluzi ostatak sustava.
 * Bez njega baza biljezi zamrznuti trenutak scenarija, a dnevnik stvarno
 * vrijeme pokretanja, pa isti dogadaj u dva ispisa nosi dva vremena.
 */
final class FrozenClock
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(datetime: CarbonImmutable::now()->toDateTimeImmutable());
    }
}
