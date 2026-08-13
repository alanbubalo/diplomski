<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * Ishod tumacenja jednog odgovora posrednika.
 *
 * Kada tumacenje nije jednoznacno, poticaj je null. To je namjerno: sustav
 * koji ne zna sto se dogodilo ne smije imati prijelaz na raspolaganju. Rupa
 * ostaje vidljiva umjesto da se popuni pretpostavkom.
 */
final readonly class Tumacenje
{
    private function __construct(
        public ?Poticaj $poticaj,
        public bool $jednoznacno,
        public string $obrazlozenje,
    ) {}

    public static function jednoznacno(Poticaj $poticaj, string $obrazlozenje): self
    {
        return new self($poticaj, true, $obrazlozenje);
    }

    public static function dvoznacno(string $obrazlozenje): self
    {
        return new self(null, false, $obrazlozenje);
    }
}
