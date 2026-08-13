<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domena\Namjera;
use App\Domena\Odgovor;
use App\Domena\Poticaj;
use App\Domena\TumacOdgovora;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Jezgra slucaja B2: ista sifra, dva znacenja, razlucena samo zapisanom
 * namjerom.
 */
final class TumacOdgovoraTest extends TestCase
{
    private TumacOdgovora $tumac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tumac = new TumacOdgovora;
    }

    #[Test]
    public function s008_uz_zapisan_ponovni_pokusaj_znaci_potvrdu(): void
    {
        $tumacenje = $this->tumac->protumaci(Odgovor::S008, Namjera::PONOVNI_POKUSAJ);

        $this->assertTrue($tumacenje->jednoznacno);
        $this->assertSame(Poticaj::POTVRDA_IZ_S008, $tumacenje->poticaj);
    }

    #[Test]
    public function s008_uz_zapisan_ispravak_znaci_odbijenicu(): void
    {
        $tumacenje = $this->tumac->protumaci(Odgovor::S008, Namjera::ISPRAVAK);

        $this->assertTrue($tumacenje->jednoznacno);
        $this->assertSame(Poticaj::ODBIJENICA_IZ_S008, $tumacenje->poticaj);
    }

    #[Test]
    public function ista_sifra_uz_dvije_namjere_daje_dva_suprotna_poticaja(): void
    {
        $ponovni = $this->tumac->protumaci(Odgovor::S008, Namjera::PONOVNI_POKUSAJ);
        $ispravak = $this->tumac->protumaci(Odgovor::S008, Namjera::ISPRAVAK);

        $this->assertNotSame($ponovni->poticaj, $ispravak->poticaj);
    }

    #[Test]
    public function s008_bez_zapisane_namjere_ostaje_dvoznacan(): void
    {
        $tumacenje = $this->tumac->protumaci(Odgovor::S008, null);

        $this->assertFalse($tumacenje->jednoznacno);
        $this->assertNull($tumacenje->poticaj);
    }

    #[Test]
    public function izostanak_odgovora_nikada_nije_jednoznacan(): void
    {
        foreach ([Namjera::PRVO_SLANJE, Namjera::PONOVNI_POKUSAJ, Namjera::ISPRAVAK, null] as $namjera) {
            $tumacenje = $this->tumac->protumaci(Odgovor::ISTEK_VREMENA, $namjera);

            $this->assertFalse($tumacenje->jednoznacno);
        }
    }
}
