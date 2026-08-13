<?php

declare(strict_types=1);

namespace App\Domena;

use Carbon\CarbonInterface;
use DomainException;

/**
 * Automat zivotnog ciklusa predaje.
 *
 * Schneiderova karakterizacija (odjeljak 5.3) trazi da izlazi budu odredeni
 * NIZOM OBRADENIH ZAHTJEVA. Iz toga slijede dva pravila koja ovaj razred
 * provodi doslovno:
 *
 *   1. Prijelaz pokrece poticaj, nikada protek vremena sam po sebi. Zato je
 *      ROK_PROTEKAO poticaj koji netko mora dostaviti, a ne pozadinski posao
 *      koji tise mijenja redak u bazi.
 *   2. Rok ulazi kao UVJET NAD PRIJELAZOM. Ponovna predaja je dopustena samo
 *      dok roka ima; kad ga nema, jedini dopusten prijelaz vodi u ROK_ISTEKAO.
 *
 * Nedopusten prijelaz je iznimka, ne tiho ignoriranje. Sustav koji propusti
 * nedopusten prijelaz prestaje biti dokaz o vlastitom stanju.
 */
final class Automat
{
    /** @var array<string, array<string, Stanje>> */
    private const PRIJELAZI = [
        Stanje::PRIPREMLJENO->value => [
            Poticaj::PREDANO->value => Stanje::PREDANO_NEPOTVRDENO,
        ],
        Stanje::PREDANO_NEPOTVRDENO->value => [
            Poticaj::PREDANO->value => Stanje::PREDANO_NEPOTVRDENO,
            Poticaj::POTVRDA->value => Stanje::POTVRDENO,
            Poticaj::POTVRDA_IZ_S008->value => Stanje::POTVRDENO,
            Poticaj::ODBIJENICA_IZ_S008->value => Stanje::ODBIJEN_ISPRAVAK,
            Poticaj::ROK_PROTEKAO->value => Stanje::ROK_ISTEKAO,
        ],
    ];

    public function dopusten(Stanje $iz, Poticaj $poticaj): bool
    {
        return isset(self::PRIJELAZI[$iz->value][$poticaj->value]);
    }

    /**
     * $rok je null dok nemogucnost nije nastupila -- tada nema od cega teci,
     * pa uvjet roka otpada.
     *
     * @throws DomainException kada prijelaz nije dopusten ili uvjet roka ne prolazi
     */
    public function prijelaz(Stanje $iz, Poticaj $poticaj, ?Rok $rok, CarbonInterface $sada): Stanje
    {
        if (! $this->dopusten($iz, $poticaj)) {
            throw new DomainException(
                sprintf('Prijelaz %s --%s--> nije dopusten.', $iz->value, $poticaj->value)
            );
        }

        $this->provjeriUvjetRoka($poticaj, $rok, $sada);

        return self::PRIJELAZI[$iz->value][$poticaj->value];
    }

    /** Rok kao uvjet nad prijelazom, ne kao stanje. */
    private function provjeriUvjetRoka(Poticaj $poticaj, ?Rok $rok, CarbonInterface $sada): void
    {
        if ($rok === null) {
            if ($poticaj === Poticaj::ROK_PROTEKAO) {
                throw new DomainException('Rok nije poceo teci: nemogucnost nije nastupila.');
            }

            return;
        }

        $istrosen = $rok->istrosen($sada);

        if ($poticaj === Poticaj::PREDANO && $istrosen) {
            throw new DomainException(
                'Ponovna predaja nije dopustena: rok iz cl. 49. st. 1. je istrosen.'
            );
        }

        if ($poticaj === Poticaj::ROK_PROTEKAO && ! $istrosen) {
            throw new DomainException(
                sprintf('Rok jos tece, preostalo %d s.', $rok->preostaloSekundi($sada))
            );
        }
    }
}
