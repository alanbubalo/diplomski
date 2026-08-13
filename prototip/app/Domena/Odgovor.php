<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * Sto posrednik moze vratiti.
 *
 * Stub vraca samo ovo troje. Prototip ne provjerava UBL, ne potpisuje i ne
 * govori AS4 -- tvrda granica opsega. Predmet je iskljucivo
 * kako poslovni sustav reagira na ova tri ishoda.
 *
 * ISTEK_VREMENA nije odgovor nego njegov izostanak. Sinkroni kanal prema
 * Sustavu za fiskalizaciju nema propisan istek vremena ni ponovni pokusaj
 * (odjeljak 6.2, slucaj B1), pa je trajanje cekanja izbor izvedbe.
 */
enum Odgovor: string
{
    case USPJEH = 'uspjeh';
    case ISTEK_VREMENA = 'istek-vremena';
    case S008 = 's008';

    public function opis(): string
    {
        return match ($this) {
            self::USPJEH => 'posrednik je potvrdio primitak',
            self::ISTEK_VREMENA => 'odgovor nije stigao u zadanom vremenu',
            self::S008 => 'S008: vec postoji fiskaliziran eRacun s istim slozenim identifikatorom',
        };
    }
}
