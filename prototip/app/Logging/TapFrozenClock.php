<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Prikljucuje obradivac FrozenClock na kanal dnevnika.
 *
 * Laravel ovaj oblik zove "tap": razred koji dobije vec sastavljen kanal i smije
 * ga dopuniti.
 */
final class TapFrozenClock
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new FrozenClock);
    }
}
