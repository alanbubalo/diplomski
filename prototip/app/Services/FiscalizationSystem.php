<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\IntermediaryResponse;
use RuntimeException;

/**
 * Kontrolirani dvojnik Sustava za fiskalizaciju. Skripta ishoda cini scenarij
 * D1 ponovljivim: prvi poziv ostaje bez odgovora, sljedeci potvrduje oporavak.
 */
final class FiscalizationSystem implements SubmissionEndpoint
{
    private int $calls = 0;

    /** @param list<IntermediaryResponse> $script */
    public function __construct(
        private readonly array $script,
    ) {
        if ($script === []) {
            throw new RuntimeException('Skripta Sustava za fiskalizaciju ne smije biti prazna.');
        }
    }

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
