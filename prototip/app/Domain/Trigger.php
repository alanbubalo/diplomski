<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Dogadaji koji pomicu automat. Isteka vremena nema medu njima: dokument koji
 * je poslan i nije potvrden ostaje poslan i nepotvrden.
 *
 * Dva poticaja nose sifru S008. Sifra je jedna, znacenja su dva, a razlucuje ih
 * zapisana namjera (vidi ResponseInterpreter).
 */
enum Trigger: string
{
    case SENT = 'sent';
    case CONFIRMATION = 'confirmation';
    case CONFIRMATION_FROM_S008 = 'confirmation-from-s008';
    case REJECTION_FROM_S008 = 'rejection-from-s008';
    case DEADLINE_ELAPSED = 'deadline-elapsed';
}
