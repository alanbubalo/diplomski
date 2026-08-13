<?php

declare(strict_types=1);

namespace App\Dnevnik;

use Illuminate\Log\Logger;

/**
 * Prikljucuje obradivac ZamrznutiSat na kanal dnevnika.
 *
 * Laravel ovaj oblik zove "tap": razred koji dobije vec sastavljen kanal i
 * smije ga dopuniti.
 */
final class PrijaviZamrznutiSat
{
    public function __invoke(Logger $dnevnik): void
    {
        $dnevnik->pushProcessor(new ZamrznutiSat);
    }
}
