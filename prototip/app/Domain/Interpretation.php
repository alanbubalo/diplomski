<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Ishod tumacenja jednog odgovora posrednika.
 *
 * Kada tumacenje nije jednoznacno, poticaj je null. To je namjerno: sustav koji
 * ne zna sto se dogodilo ne smije imati prijelaz na raspolaganju. Rupa ostaje
 * vidljiva umjesto da se popuni pretpostavkom.
 */
final readonly class Interpretation
{
    private function __construct(
        public ?Trigger $trigger,
        public bool $unambiguous,
        public string $reason,
    ) {}

    public static function unambiguous(Trigger $trigger, string $reason): self
    {
        return new self($trigger, true, $reason);
    }

    public static function ambiguous(string $reason): self
    {
        return new self(null, false, $reason);
    }
}
