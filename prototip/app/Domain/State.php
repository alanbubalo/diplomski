<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Stanja u kojima se predaja dokumenta moze naci.
 *
 * SENT_UNCONFIRMED je stanje koje ni Zakon, ni Pravilnik, ni tehnicka
 * specifikacija ne imenuju. Odjeljak 6.3 rada tvrdi da ga sustav svejedno mora
 * imenovati, jer ga inace mora lagati u jedno od dva susjedna stanja. Ovaj enum
 * je izvedba te tvrdnje.
 *
 * DEADLINE_EXPIRED nije stanje u koje se ulazi protekom vremena. U njega se
 * ulazi pokusajem koji naidje na istrosen rok -- rok je uvjet nad prijelazom,
 * ne stanje. Razlog je u odjeljku 5.3: automat je odreden nizom poruka.
 */
enum State: string
{
    case PREPARED = 'prepared';
    case SENT_UNCONFIRMED = 'sent-unconfirmed';
    case CONFIRMED = 'confirmed';
    case CORRECTION_REJECTED = 'correction-rejected';
    case DEADLINE_EXPIRED = 'deadline-expired';

    /** Stanja iz kojih vise nema prijelaza. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::CORRECTION_REJECTED, self::DEADLINE_EXPIRED => true,
            default => false,
        };
    }

    /**
     * Zna li poslovni sustav ishod fiskalizacije?
     *
     * Ovo je razlika koju klasifikacija iz odjeljka 6.2 zove "neodredeno":
     * nije rijec o losem ishodu nego o ishodu koji sustav ne moze saznati.
     */
    public function outcomeKnown(): bool
    {
        return $this !== self::SENT_UNCONFIRMED;
    }

    /** Hrvatski opis za dokazni ispis u poglavlju 7. */
    public function description(): string
    {
        return match ($this) {
            self::PREPARED => 'namjera zapisana, predaja jos nije pokusana',
            self::SENT_UNCONFIRMED => 'poslano, potvrda nije stigla -- ishod nepoznat',
            self::CONFIRMED => 'posrednik je potvrdio primitak',
            self::CORRECTION_REJECTED => 'propisani ispravak odbijen sifrom S008',
            self::DEADLINE_EXPIRED => 'rok iz cl. 49. st. 1. istrosen prije potvrde',
        };
    }
}
