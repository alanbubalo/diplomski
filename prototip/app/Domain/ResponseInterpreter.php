<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tumacenje odgovora posrednika uz zapisanu namjeru.
 *
 * Ovo je jezgra slucaja B2 i, uz StateMachine, najvazniji razred prototipa.
 *
 * Sifra S008 znaci istovremeno "tvoj ponovni pokusaj je prosao" i "tvoj
 * propisani ispravak je odbijen". Poruka ne nosi polje koje bi to razlikovalo,
 * pa razlikovanje mora biti LOKALNO. Poslovni sustav zna nesto sto Sustav za
 * fiskalizaciju ne zna: zna je li poslao ponovni pokusaj ili ispravak.
 *
 * Metoda prima $intent kao nullable upravo zato da se moze pokazati sto se
 * dogodi kada zapisa nema. Tada povratna vrijednost nije pogodena nego
 * dvoznacna, i to je nalaz, ne kvar prototipa.
 */
final class ResponseInterpreter
{
    public function interpret(IntermediaryResponse $response, ?Intent $intent): Interpretation
    {
        return match ($response) {
            IntermediaryResponse::SUCCESS => Interpretation::unambiguous(
                Trigger::CONFIRMATION,
                'posrednik je potvrdio primitak',
            ),

            // Izostanak odgovora ne pomice automat. Dokument ostaje poslan i
            // nepotvrden, sto je tocan opis onoga sto sustav zna.
            IntermediaryResponse::TIMEOUT => Interpretation::ambiguous(
                'odgovor nije stigao; nije poznato je li poruka obradena',
            ),

            IntermediaryResponse::S008 => $this->interpretS008($intent),
        };
    }

    private function interpretS008(?Intent $intent): Interpretation
    {
        return match ($intent) {
            Intent::RETRY => Interpretation::unambiguous(
                Trigger::CONFIRMATION_FROM_S008,
                'S008 uz zapisan ponovni pokusaj dokazuje da je raniji pokusaj prosao',
            ),

            Intent::CORRECTION => Interpretation::unambiguous(
                Trigger::REJECTION_FROM_S008,
                'S008 uz zapisan ispravak znaci odbijenicu propisanog tijeka ispravka',
            ),

            // Prvo slanje koje odmah dobije S008 znaci da je isti slozeni
            // identifikator vec fiskaliziran, a ovaj ga posiljatelj nije
            // poslao. To je nalaz koji trazi ljudsko postupanje.
            Intent::FIRST_SEND => Interpretation::ambiguous(
                'S008 na prvo slanje: slozeni identifikator vec postoji, izvor nije poznat',
            ),

            null => Interpretation::ambiguous(
                'S008 bez zapisane namjere: ista sifra znaci i potvrdu i odbijenicu',
            ),
        };
    }
}
