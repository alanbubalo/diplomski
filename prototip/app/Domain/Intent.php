<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * POSLOVNA namjera s kojom je dokument poslan.
 *
 * Namjera opisuje sto je dokument, ne koji je ovo pokusaj dostave. Ta dva
 * podatka su razdvojena namjerno: ponavljanje ne mijenja poslovnu namjeru, pa
 * ponovljena dostava ispravka ostaje ispravak. Redni pokusaj dostave nosi
 * stupac `attempt`, a ne ovaj enum.
 *
 * Razdvajanje je uvjet da se odgovor uopce moze tumaciti. Sustav za
 * fiskalizaciju na ponovljenu dostavu izvornika i na ispravak sa zadrzanim
 * identifikatorom odgovara istom sifrom S008, a poruka ne nosi polje koje bi ta
 * dva razlikovala. Posiljatelj zna razliku jer zna sto je namjeravao. Ako to
 * zapise prije slanja, dio odgovora postaje jednoznacan pri tumacenju iako je
 * dvoznacan pri primitku.
 *
 * Zapis pritom ne razrjesava svaki spoj. Ponovljena dostava ispravka sa
 * zadrzanim identifikatorom ostaje dvoznacna i uz zapisanu namjeru, jer se S008
 * moze odnositi i na izvornik i na vlastiti raniji pokusaj. Vidi
 * ResponseInterpreter.
 */
enum Intent: string
{
    case ORIGINAL = 'original';
    case CORRECTION = 'correction';

    /** Hrvatski opis za dokazni ispis u poglavlju 7. */
    public function description(): string
    {
        return match ($this) {
            self::ORIGINAL => 'izvorno izdavanje dokumenta',
            self::CORRECTION => 'ispravak pod istim brojem racuna (HR-CIUS, indikator kopije)',
        };
    }
}
