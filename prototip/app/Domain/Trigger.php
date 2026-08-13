<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Dogadaji koji pomicu automat.
 *
 * Primijetiti da isteka vremena nema medu njima. Izostanak odgovora ne pomice
 * stanje: dokument koji je poslan i nije potvrden ostaje poslan i nepotvrden. To
 * je razlika izmedu automata koji priznaje neznanje i automata koji ga prikriva
 * pogadanjem.
 *
 * Iz istog razloga postoje DVA poticaja koja nose sifru S008. Sifra je jedna,
 * znacenja su dva, a razlucuje ih zapisana namjera (vidi ResponseInterpreter).
 */
enum Trigger: string
{
    case SENT = 'sent';
    case CONFIRMATION = 'confirmation';
    case CONFIRMATION_FROM_S008 = 'confirmation-from-s008';
    case REJECTION_FROM_S008 = 'rejection-from-s008';
    case DEADLINE_ELAPSED = 'deadline-elapsed';
}
