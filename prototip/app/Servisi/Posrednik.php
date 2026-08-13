<?php

declare(strict_types=1);

namespace App\Servisi;

use App\Domena\Odgovor;
use RuntimeException;

/**
 * Stub informacijskog posrednika.
 *
 * Ovo NIJE pristupna tocka. Ne govori AS4, ne provjerava UBL, ne potpisuje i
 * ne otkriva adresu primatelja. Vraca jedan od tri ishoda po unaprijed zadanoj
 * skripti, i time cini scenarije ponovljivima.
 *
 * Granica je namjerna. Predmet rada je kako poslovni sustav rukuje greskom, a
 * ne je li posrednik sukladan. Vjerodostojna izvedba posrednika ne bi dodala
 * nijedan nalaz, a udvostrucila bi opseg.
 */
final class Posrednik
{
    private int $pozvan = 0;

    /** @param list<Odgovor> $skripta */
    public function __construct(
        private readonly array $skripta,
    ) {
        if ($skripta === []) {
            throw new RuntimeException('Skripta posrednika ne smije biti prazna.');
        }
    }

    /**
     * Zadnji ishod u skripti ponavlja se za sve daljnje pozive, pa scenarij
     * koji ponavlja do isteka roka ne mora nabrajati svaki pokusaj.
     */
    public function predaj(string $kljucPosiljatelja, string $slozeniIdentifikator): Odgovor
    {
        $indeks = min($this->pozvan, count($this->skripta) - 1);
        $this->pozvan++;

        return $this->skripta[$indeks];
    }

    public function brojPoziva(): int
    {
        return $this->pozvan;
    }
}
