<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * S kojom je namjerom poruka poslana.
 *
 * Ovo je jedini podatak koji razrjesava slucaj B2. Sustav za fiskalizaciju na
 * RETRY i na CORRECTION odgovara istom sifrom S008, a poruka ne nosi polje koje
 * bi ta dva razlikovalo. Posiljatelj zna razliku jer zna sto je namjeravao. Ako
 * to zapise prije slanja, odgovor postaje jednoznacan pri tumacenju iako je
 * dvoznacan pri primitku.
 *
 * Ako se zapis izgubi, razlikovanja vise nema i rekonstruirati ga iz podataka
 * Sustava za fiskalizaciju nije moguce. Zato B2 ostaje regulatorno nesvodiv.
 */
enum Intent: string
{
    case FIRST_SEND = 'first-send';
    case RETRY = 'retry';
    case CORRECTION = 'correction';

    /** Hrvatski opis za dokazni ispis u poglavlju 7. */
    public function description(): string
    {
        return match ($this) {
            self::FIRST_SEND => 'prvo slanje dokumenta',
            self::RETRY => 'ponavljanje poruke koja je ostala bez odgovora',
            self::CORRECTION => 'ispravak pod istim brojem racuna (HR-CIUS, indikator kopije)',
        };
    }
}
