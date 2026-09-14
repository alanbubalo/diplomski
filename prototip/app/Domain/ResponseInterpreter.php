<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tumacenje odgovora odredista uz zapisanu poslovnu namjeru i redni pokusaj.
 * Jezgra slucaja B2.
 *
 * Sifra S008 kaze samo da kod odredista postoji zapis s istim slozenim
 * identifikatorom i istom vrstom eRacuna. Sto posiljatelj iz nje smije
 * zakljuciti ovisi o dva lokalna podatka: sto je dokument i je li ovo prva ili
 * ponovljena dostava. $intent je nullable da se vidi ishod kada zapisa nema.
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

            // Izostanak odgovora ne pomice automat.
            IntermediaryResponse::TIMEOUT => Interpretation::ambiguous(
                'odgovor nije stigao; nije poznato je li poruka obradena',
            ),

            IntermediaryResponse::S008 => $this->interpretS008($intent, $repeatDelivery),
        };
    }

    /**
     * Cetiri spoja namjere i rednog pokusaja daju tri ishoda. Samo su dva
     * jednoznacna: zapis namjere dvoznacnost suzava, ne uklanja je.
     */
    private function interpretS008(?Intent $intent, bool $repeatDelivery): Interpretation
    {
        return match (true) {
            // Ponovljena dostava izvornika. Raniji pokusaj je prosao, uz
            // pretpostavku da je poruka nepromijenjena i da identifikator nije
            // ranije upotrijebljen za drugi sadrzaj.
            $intent === Intent::ORIGINAL && $repeatDelivery => Interpretation::unambiguous(
                Trigger::CONFIRMATION_FROM_S008,
                'S008 na ponovljenu dostavu nepromijenjenog izvornika: raniji pokusaj je prosao,'
                .' uz pretpostavku da identifikator nije upotrijebljen za drugi sadrzaj',
            ),

            // Prva dostava ispravka. Zapis koji S008 prijavljuje je izvornik,
            // jer ga ovaj posiljatelj u ovoj predaji nije mogao stvoriti.
            $intent === Intent::CORRECTION && ! $repeatDelivery => Interpretation::unambiguous(
                Trigger::REJECTION_FROM_S008,
                'S008 na prvu dostavu ispravka sa zadrzanim identifikatorom: ta grana ispravka je odbijena',
            ),

            // Ponovljena dostava ispravka. Sifra se moze odnositi na izvornik
            // ili na vlastiti raniji pokusaj, a identifikator je u obama isti.
            $intent === Intent::CORRECTION => Interpretation::ambiguous(
                'S008 na ponovljenu dostavu ispravka sa zadrzanim identifikatorom:'
                .' odnosi se na izvornik ili na vlastiti raniji pokusaj, razlika se lokalno ne moze utvrditi',
            ),

            // Isti identifikator je vec fiskaliziran, a ovaj ga posiljatelj
            // nije poslao. Trazi ljudsko postupanje.
            $intent === Intent::ORIGINAL => Interpretation::ambiguous(
                'S008 na prvu dostavu izvornika: slozeni identifikator vec postoji, izvor nije poznat',
            ),

            default => Interpretation::ambiguous(
                'S008 bez zapisane namjere: ista sifra znaci i potvrdu i odbijenicu',
            ),
        };
    }
}
