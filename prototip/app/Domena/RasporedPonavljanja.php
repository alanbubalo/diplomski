<?php

declare(strict_types=1);

namespace App\Domena;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dva rasporeda ponavljanja, jedan pokraj drugog, da se razlika vidi.
 *
 * Eksponencijalni odmak razmak POVECAVA kako pokusaji rastu. Rok iz cl. 49.
 * st. 1. istovremeno se SMANJUJE, jer tece od nastupa nemogucnosti. Dva se
 * kretanja razilaze, i to je razlog zbog kojeg uobicajeni obrazac ovdje ne
 * zadovoljava uvjet koji skupina C postavlja.
 *
 * Raspored svjestan roka radi obrnuto: sljedeci pokusaj stavlja na polovicu
 * PREOSTALOG roka, pa se pokusaji zgusnjavaju kako rok tanji. Uz to nikada
 * ne prelazi rok, jer je polovica preostalog uvijek unutar preostalog.
 *
 * Slucajni razmak (Brooker) namjerno je iskljucen po zadanom. Dokazni ispis
 * u poglavlju 7 mora biti ponovljiv, a jedan posiljatelj u prototipu ionako
 * nema s kim se sudariti. U pogonu bi bio ukljucen.
 */
final readonly class RasporedPonavljanja
{
    private const NAJMANJI_RAZMAK_S = 60;

    public function __construct(
        private bool $slucajniRazmak = false,
    ) {}

    /**
     * Sljedeci pokusaj na polovici preostalog roka.
     *
     * Vraca null kada roka vise nema -- tada se ne ponavlja nego se prelazi
     * u ROK_ISTEKAO, sto je uvjet nad prijelazom iz Automata.
     */
    public function svjestanRoka(Rok $rok, CarbonInterface $sada): ?CarbonImmutable
    {
        $preostalo = $rok->preostaloSekundi($sada);

        if ($preostalo <= 0) {
            return null;
        }

        $razmak = max(self::NAJMANJI_RAZMAK_S, intdiv($preostalo, 2));

        if ($this->slucajniRazmak) {
            $razmak = random_int(intdiv($razmak, 2), $razmak);
        }

        return CarbonImmutable::instance($sada)->addSeconds(min($razmak, $preostalo));
    }

    /**
     * Klasicni eksponencijalni odmak, ovdje samo za usporedbu.
     *
     * Ne prima rok jer ga i ne gleda. To je cijela poanta.
     */
    public function eksponencijalniOdmak(int $pokusaj, CarbonInterface $sada): CarbonImmutable
    {
        $razmak = self::NAJMANJI_RAZMAK_S * (2 ** max(0, $pokusaj - 1));

        return CarbonImmutable::instance($sada)->addSeconds($razmak);
    }
}
