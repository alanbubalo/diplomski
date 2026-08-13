<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * Stanja u kojima se predaja dokumenta moze naci.
 *
 * PREDANO_NEPOTVRDENO je stanje koje ni Zakon, ni Pravilnik, ni tehnicka
 * specifikacija ne imenuju. Odjeljak 6.3 rada tvrdi da ga sustav svejedno
 * mora imenovati, jer ga inace mora lagati u jedno od dva susjedna stanja.
 * Ovaj enum je izvedba te tvrdnje.
 *
 * ROK_ISTEKAO nije stanje u koje se ulazi protekom vremena. U njega se ulazi
 * pokusajem koji naidje na istrosen rok -- rok je uvjet nad prijelazom, ne
 * stanje. Razlog je u odjeljku 5.3: automat je odreden nizom poruka.
 */
enum Stanje: string
{
    case PRIPREMLJENO = 'pripremljeno';
    case PREDANO_NEPOTVRDENO = 'predano-nepotvrdeno';
    case POTVRDENO = 'potvrdeno';
    case ODBIJEN_ISPRAVAK = 'odbijen-ispravak';
    case ROK_ISTEKAO = 'rok-istekao';

    /** Stanja iz kojih vise nema prijelaza. */
    public function konacno(): bool
    {
        return match ($this) {
            self::POTVRDENO, self::ODBIJEN_ISPRAVAK, self::ROK_ISTEKAO => true,
            default => false,
        };
    }

    /**
     * Zna li poslovni sustav ishod fiskalizacije?
     *
     * Ovo je razlika koju klasifikacija iz odjeljka 6.2 zove "neodredeno":
     * nije rijec o losem ishodu nego o ishodu koji sustav ne moze saznati.
     */
    public function ishodPoznat(): bool
    {
        return $this !== self::PREDANO_NEPOTVRDENO;
    }

    public function opis(): string
    {
        return match ($this) {
            self::PRIPREMLJENO => 'namjera zapisana, predaja jos nije pokusana',
            self::PREDANO_NEPOTVRDENO => 'predano, potvrda nije stigla -- ishod nepoznat',
            self::POTVRDENO => 'posrednik je potvrdio primitak',
            self::ODBIJEN_ISPRAVAK => 'propisani ispravak odbijen sifrom S008',
            self::ROK_ISTEKAO => 'rok iz cl. 49. st. 1. istrosen prije potvrde',
        };
    }
}
