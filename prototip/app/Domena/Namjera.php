<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * S kojom je namjerom poruka poslana.
 *
 * Ovo je jedini podatak koji razrjesava slucaj B2. Sustav za fiskalizaciju
 * na PONOVNI_POKUSAJ i na ISPRAVAK odgovara istom sifrom S008, a poruka ne
 * nosi polje koje bi ta dva razlikovalo. Posiljatelj zna razliku jer zna sto
 * je namjeravao. Ako to zapise prije slanja, odgovor postaje jednoznacan pri
 * tumacenju iako je dvoznacan pri primitku.
 *
 * Ako se zapis izgubi, razlikovanja vise nema i rekonstruirati ga iz podataka
 * Sustava za fiskalizaciju nije moguce. Zato B2 ostaje regulatorno nesvodiv.
 */
enum Namjera: string
{
    case PRVO_SLANJE = 'prvo-slanje';
    case PONOVNI_POKUSAJ = 'ponovni-pokusaj';
    case ISPRAVAK = 'ispravak';

    public function opis(): string
    {
        return match ($this) {
            self::PRVO_SLANJE => 'prvo slanje dokumenta',
            self::PONOVNI_POKUSAJ => 'ponavljanje poruke koja je ostala bez odgovora',
            self::ISPRAVAK => 'ispravak pod istim brojem racuna (HR-CIUS, indikator kopije)',
        };
    }
}
