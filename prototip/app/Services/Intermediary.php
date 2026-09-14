<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\IntermediaryResponse;
use RuntimeException;

/**
 * Stub informacijskog posrednika, ne pristupna tocka. Vraca jedan od tri ishoda
 * po unaprijed zadanoj skripti i time cini scenarije ponovljivima.
 *
 * Predmet rada je kako poslovni sustav rukuje greskom, a ne je li posrednik
 * sukladan, pa stub ne govori AS4, ne provjerava UBL i ne potpisuje.
 */
final class Intermediary implements SubmissionEndpoint
{
    private int $calls = 0;

    /** @param list<IntermediaryResponse> $script */
    public function __construct(
        private readonly array $script,
    ) {
        if ($script === []) {
            throw new RuntimeException('Skripta posrednika ne smije biti prazna.');
        }
    }

    /** Zadnji ishod u skripti ponavlja se za sve daljnje pozive. */
    public function send(string $senderKey, string $compositeIdentifier): IntermediaryResponse
    {
        $index = min($this->calls, count($this->script) - 1);
        $this->calls++;

        return $this->script[$index];
    }

    public function callCount(): int
    {
        return $this->calls;
    }
}
