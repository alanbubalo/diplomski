<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Stanja u kojima se predaja dokumenta moze naci.
 *
 * SENT_UNCONFIRMED ne imenuje ni Zakon, ni Pravilnik, ni tehnicka
 * specifikacija; odjeljak 6.3 tvrdi da ga sustav svejedno mora imenovati.
 * U DEADLINE_EXPIRED se ne ulazi protekom vremena nego pokusajem koji naidje
 * na istrosen rok, jer je rok uvjet nad prijelazom (odjeljak 5.3).
 */
enum State: string
{
    case PREPARED = 'prepared';
    case SENT_UNCONFIRMED = 'sent-unconfirmed';
    case CONFIRMED = 'confirmed';
    case CORRECTION_REJECTED = 'correction-rejected';
    case DEADLINE_EXPIRED = 'deadline-expired';

    public function isFinal(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::CORRECTION_REJECTED, self::DEADLINE_EXPIRED => true,
            default => false,
        };
    }

    /**
     * Zna li poslovni sustav ishod predaje? Istek roka ne razrjesava raniji
     * poziv. To je klasa koju odjeljak 6.2 zove "neodredeno".
     */
    public function outcomeKnown(): bool
    {
        return ! in_array($this, [self::SENT_UNCONFIRMED, self::DEADLINE_EXPIRED], true);
    }

    /** Hrvatski opis za dokazni ispis u poglavlju 7. */
    public function description(): string
    {
        return match ($this) {
            self::PREPARED => 'namjera zapisana, predaja jos nije pokusana',
            self::SENT_UNCONFIRMED => 'poslano, potvrda nije stigla -- ishod nepoznat',
            self::CONFIRMED => 'odrediste je potvrdilo primitak',
            self::CORRECTION_REJECTED => 'propisani ispravak odbijen sifrom S008',
            self::DEADLINE_EXPIRED => 'rok iz cl. 49. st. 1. istekao prije potvrde;'
                .' ishod ranijeg slanja ostaje nepoznat',
        };
    }
}
