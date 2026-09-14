<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Poslovna namjera s kojom je dokument poslan.
 *
 * Namjera opisuje sto je dokument, ne koji je ovo pokusaj dostave; redni
 * pokusaj nosi stupac `attempt`. Razdvojeni su jer ponavljanje ne mijenja
 * namjeru, a tumacenje sifre S008 trazi oba podatka (vidi ResponseInterpreter).
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
