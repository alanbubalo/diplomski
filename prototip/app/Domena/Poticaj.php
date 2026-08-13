<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * Dogadaji koji pomicu automat.
 *
 * Primijetiti da ISTEK_VREMENA nije medu njima. Izostanak odgovora ne pomice
 * stanje: dokument koji je predan i nije potvrden ostaje predan i nepotvrden.
 * To je razlika izmedu automata koji priznaje neznanje i automata koji ga
 * prikriva pogadanjem.
 *
 * Iz istog razloga postoje DVA poticaja koja nose sifru S008. Sifra je jedna,
 * znacenja su dva, a razlucuje ih zapisana namjera (vidi TumacOdgovora).
 */
enum Poticaj: string
{
    case PREDANO = 'predano';
    case POTVRDA = 'potvrda';
    case POTVRDA_IZ_S008 = 'potvrda-iz-s008';
    case ODBIJENICA_IZ_S008 = 'odbijenica-iz-s008';
    case ROK_PROTEKAO = 'rok-protekao';
}
