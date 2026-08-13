<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Sto posrednik moze vratiti.
 *
 * Stub vraca samo ovo troje. Prototip ne provjerava UBL, ne potpisuje i ne
 * govori AS4 -- tvrda granica opsega. Predmet je iskljucivo kako
 * poslovni sustav reagira na ova tri ishoda.
 *
 * TIMEOUT nije odgovor nego njegov izostanak. Sinkroni kanal prema Sustavu za
 * fiskalizaciju nema propisan istek vremena ni ponovni pokusaj (odjeljak 6.2,
 * slucaj B1), pa je trajanje cekanja izbor izvedbe.
 */
enum IntermediaryResponse: string
{
    case SUCCESS = 'success';
    case TIMEOUT = 'timeout';
    case S008 = 's008';

    /** Hrvatski opis za dokazni ispis u poglavlju 7. */
    public function description(): string
    {
        return match ($this) {
            self::SUCCESS => 'posrednik je potvrdio primitak',
            self::TIMEOUT => 'odgovor nije stigao u zadanom vremenu',
            self::S008 => 'S008: vec postoji fiskaliziran eRacun s istim slozenim identifikatorom',
        };
    }
}
