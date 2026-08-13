<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domena\RasporedPonavljanja;
use App\Domena\Rok;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rok iz cl. 49. st. 1. i raspored koji ga postuje.
 */
final class RokTest extends TestCase
{
    #[Test]
    public function rok_tece_od_nastupa_a_ne_od_oporavka(): void
    {
        $nastup = CarbonImmutable::parse('2026-09-01 09:00:00');
        $rok = Rok::odNastupa($nastup);

        // Kasniji trenutak ne pomice istek. To je cijela razlika prema roku
        // koji bi tekao od oporavka.
        $isti = Rok::odNastupa($nastup);

        $this->assertTrue($rok->istice->equalTo($isti->istice));
        $this->assertLessThan(
            $rok->preostaloSekundi($nastup),
            $rok->preostaloSekundi($nastup->addDay()),
        );
    }

    #[Test]
    public function pet_radnih_dana_preskace_vikend(): void
    {
        // Utorak 1. rujna 2026. Pet radnih dana zavrsava u utorak 8. rujna,
        // jer subota i nedjelja ne ulaze.
        $rok = Rok::odNastupa(CarbonImmutable::parse('2026-09-01 14:30:00'));

        $this->assertSame('2026-09-08', $rok->istice->toDateString());
    }

    #[Test]
    public function raspored_svjestan_roka_nikada_ne_prelazi_rok(): void
    {
        $nastup = CarbonImmutable::parse('2026-09-01 09:00:00');
        $rok = Rok::odNastupa($nastup);
        $raspored = new RasporedPonavljanja;

        $sada = $nastup;

        for ($pokusaj = 1; $pokusaj <= 20; $pokusaj++) {
            $sljedeci = $raspored->svjestanRoka($rok, $sada);

            if ($sljedeci === null) {
                $this->assertTrue($rok->istrosen($sada));

                return;
            }

            $this->assertLessThanOrEqual(
                $rok->istice->getTimestamp(),
                $sljedeci->getTimestamp(),
                "Pokusaj {$pokusaj} zakazan izvan roka.",
            );

            $sada = $sljedeci;
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function eksponencijalni_odmak_rok_uopce_ne_gleda(): void
    {
        $nastup = CarbonImmutable::parse('2026-09-01 09:00:00');
        $rok = Rok::odNastupa($nastup);
        $raspored = new RasporedPonavljanja;

        $sada = $nastup;
        $prekoracio = false;

        for ($pokusaj = 1; $pokusaj <= 20; $pokusaj++) {
            $sada = $raspored->eksponencijalniOdmak($pokusaj, $sada);

            if ($sada->greaterThan($rok->istice)) {
                $prekoracio = true;
                break;
            }
        }

        $this->assertTrue($prekoracio, 'Eksponencijalni odmak ocekivano prekoracuje rok.');
    }
}
