<?php

declare(strict_types=1);

namespace App\Domena;

/**
 * Tumacenje odgovora posrednika uz zapisanu namjeru.
 *
 * Ovo je jezgra slucaja B2 i, uz Automat, najvazniji razred prototipa.
 *
 * Sifra S008 znaci istovremeno "tvoj ponovni pokusaj je prosao" i "tvoj
 * propisani ispravak je odbijen". Poruka ne nosi polje koje bi to razlikovalo,
 * pa razlikovanje mora biti LOKALNO. Poslovni sustav zna nesto sto Sustav za
 * fiskalizaciju ne zna: zna je li poslao ponovni pokusaj ili ispravak.
 *
 * Metoda prima $namjera kao nullable upravo zato da se moze pokazati sto se
 * dogodi kada zapisa nema. Tada povratna vrijednost nije pogodena nego
 * dvoznacna, i to je nalaz, ne kvar prototipa.
 */
final class TumacOdgovora
{
    public function protumaci(Odgovor $odgovor, ?Namjera $namjera): Tumacenje
    {
        return match ($odgovor) {
            Odgovor::USPJEH => Tumacenje::jednoznacno(
                Poticaj::POTVRDA,
                'posrednik je potvrdio primitak',
            ),

            // Izostanak odgovora ne pomice automat. Dokument ostaje predan i
            // nepotvrden, sto je tocan opis onoga sto sustav zna.
            Odgovor::ISTEK_VREMENA => Tumacenje::dvoznacno(
                'odgovor nije stigao; nije poznato je li poruka obradena',
            ),

            Odgovor::S008 => $this->protumaciS008($namjera),
        };
    }

    private function protumaciS008(?Namjera $namjera): Tumacenje
    {
        return match ($namjera) {
            Namjera::PONOVNI_POKUSAJ => Tumacenje::jednoznacno(
                Poticaj::POTVRDA_IZ_S008,
                'S008 uz zapisan ponovni pokusaj dokazuje da je raniji pokusaj prosao',
            ),

            Namjera::ISPRAVAK => Tumacenje::jednoznacno(
                Poticaj::ODBIJENICA_IZ_S008,
                'S008 uz zapisan ispravak znaci odbijenicu propisanog tijeka ispravka',
            ),

            // Prvo slanje koje odmah dobije S008 znaci da je isti slozeni
            // identifikator vec fiskaliziran, a ovaj ga posiljatelj nije
            // poslao. To je nalaz koji trazi ljudsko postupanje.
            Namjera::PRVO_SLANJE => Tumacenje::dvoznacno(
                'S008 na prvo slanje: slozeni identifikator vec postoji, izvor nije poznat',
            ),

            null => Tumacenje::dvoznacno(
                'S008 bez zapisane namjere: ista sifra znaci i potvrdu i odbijenicu',
            ),
        };
    }
}
