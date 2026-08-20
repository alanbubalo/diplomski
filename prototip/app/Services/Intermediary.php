<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\IntermediaryResponse;
use RuntimeException;

/**
 * Stub informacijskog posrednika.
 *
 * Ovo NIJE pristupna tocka. Ne govori AS4, ne provjerava UBL, ne potpisuje i ne
 * otkriva adresu primatelja. Vraca jedan od tri ishoda po unaprijed zadanoj
 * skripti, i time cini scenarije ponovljivima.
 *
 * Granica je namjerna. Predmet rada je kako poslovni sustav rukuje greskom, a ne
 * je li posrednik sukladan. Vjerodostojna izvedba posrednika ne bi dodala
 * nijedan nalaz, a udvostrucila bi opseg.
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

    /**
     * Zadnji ishod u skripti ponavlja se za sve daljnje pozive, pa scenarij koji
     * ponavlja do isteka roka ne mora nabrajati svaki pokusaj.
     */
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
