<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tumacenje odgovora odredista uz zapisanu poslovnu namjeru i redni pokusaj.
 *
 * Ovo je jezgra slucaja B2 i, uz StateMachine, najvazniji razred prototipa.
 *
 * Sifra S008 znaci samo da zapis s istim slozenim identifikatorom i istom
 * vrstom eRacuna kod odredista postoji. Ne kaze da je sadrzaj istovjetan, ni da
 * ga je stvorio upravo promatrani pokusaj. Sto iz nje posiljatelj smije
 * zakljuciti ovisi o dva LOKALNA podatka: o tome sto je dokument (namjera) i o
 * tome je li ovo prva ili ponovljena dostava te iste poruke.
 *
 * Metoda prima $intent kao nullable upravo zato da se moze pokazati sto se
 * dogodi kada zapisa nema. Tada povratna vrijednost nije pogodena nego
 * dvoznacna, i to je nalaz, ne kvar prototipa.
 */
final class ResponseInterpreter
{
    public function interpret(
        IntermediaryResponse $response,
        ?Intent $intent,
        bool $repeatDelivery = false,
    ): Interpretation {
        return match ($response) {
            IntermediaryResponse::SUCCESS => Interpretation::unambiguous(
                Trigger::CONFIRMATION,
                'odrediste je potvrdilo primitak',
            ),

            // Izostanak odgovora ne pomice automat. Dokument ostaje poslan i
            // nepotvrden, sto je tocan opis onoga sto sustav zna.
            IntermediaryResponse::TIMEOUT => Interpretation::ambiguous(
                'odgovor nije stigao; nije poznato je li poruka obradena',
            ),

            IntermediaryResponse::S008 => $this->interpretS008($intent, $repeatDelivery),
        };
    }

    /**
     * Cetiri spoja namjere i rednog pokusaja daju tri razlicita ishoda.
     *
     * Samo dva spoja su jednoznacna, i oba uz izrecene pretpostavke. Ostala dva
     * ostaju dvoznacna: zapis namjere suzava dvoznacnost, ne uklanja je.
     */
    private function interpretS008(?Intent $intent, bool $repeatDelivery): Interpretation
    {
        return match (true) {
            // Ponovljena dostava izvornika. S008 govori da je raniji pokusaj
            // prosao, ali samo uz tri pretpostavke: poruka je nepromijenjena,
            // slozeni identifikator nije ranije upotrijebljen za drugi sadrzaj i
            // nema konkurentnog slanja s istim kljucem.
            $intent === Intent::ORIGINAL && $repeatDelivery => Interpretation::unambiguous(
                Trigger::CONFIRMATION_FROM_S008,
                'S008 na ponovljenu dostavu nepromijenjenog izvornika: raniji pokusaj je prosao,'
                .' uz pretpostavku da identifikator nije upotrijebljen za drugi sadrzaj',
            ),

            // Prva dostava ispravka sa zadrzanim identifikatorom. Zapis koji
            // S008 prijavljuje je izvornik, jer ga ovaj posiljatelj u ovoj
            // predaji nije mogao stvoriti.
            $intent === Intent::CORRECTION && ! $repeatDelivery => Interpretation::unambiguous(
                Trigger::REJECTION_FROM_S008,
                'S008 na prvu dostavu ispravka sa zadrzanim identifikatorom: ta grana ispravka je odbijena',
            ),

            // Ponovljena dostava ispravka. Sifra se moze odnositi na izvornik
            // ili na vlastiti raniji pokusaj, a identifikator je u obama isti.
            // Zapisana namjera tu ne pomaze i to je nalaz, ne propust.
            $intent === Intent::CORRECTION => Interpretation::ambiguous(
                'S008 na ponovljenu dostavu ispravka sa zadrzanim identifikatorom:'
                .' odnosi se na izvornik ili na vlastiti raniji pokusaj, razlika se lokalno ne moze utvrditi',
            ),

            // Prva dostava izvornika koja odmah dobije S008 znaci da je isti
            // slozeni identifikator vec fiskaliziran, a ovaj ga posiljatelj nije
            // poslao. To je nalaz koji trazi ljudsko postupanje.
            $intent === Intent::ORIGINAL => Interpretation::ambiguous(
                'S008 na prvu dostavu izvornika: slozeni identifikator vec postoji, izvor nije poznat',
            ),

            default => Interpretation::ambiguous(
                'S008 bez zapisane namjere: ista sifra znaci i potvrdu i odbijenicu',
            ),
        };
    }
}
