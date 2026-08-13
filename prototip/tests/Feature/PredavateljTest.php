<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domena\Automat;
use App\Domena\Namjera;
use App\Domena\Odgovor;
use App\Domena\RasporedPonavljanja;
use App\Domena\Stanje;
use App\Domena\TumacOdgovora;
use App\Models\Predaja;
use App\Models\Racun;
use App\Servisi\Posrednik;
use App\Servisi\Predavatelj;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tvrdnje o ishodu, po jedna za svaki scenarij iz poglavlja 7.
 *
 * Svaka je pisana u uvjetnom obliku: uz zadani nacin otkazivanja i zadani
 * obrazac, zavrsno stanje mora biti odredeno. Time testovi nisu samo zastita
 * od regresije nego i izvedba oraclea koji poglavlje 8 opisuje.
 */
final class PredavateljTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function b1_izostanak_odgovora_zavrsava_u_imenovanom_stanju(): void
    {
        $predaja = $this->predavatelj([Odgovor::ISTEK_VREMENA])
            ->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        $this->assertNotNull($predaja);
        $this->assertSame(Stanje::PREDANO_NEPOTVRDENO, $predaja->stanje);
        $this->assertFalse($predaja->stanje->ishodPoznat());
        $this->assertNotNull($predaja->nastanak_nemogucnosti, 'Rok mora poceti teci od nastupa.');
        $this->assertNotNull($predaja->sljedeci_pokusaj, 'Sljedeci pokusaj mora biti zakazan.');
    }

    #[Test]
    public function b1_pokusaj_nakon_isteka_roka_zavrsava_u_rok_istekao(): void
    {
        $predavatelj = $this->predavatelj([Odgovor::ISTEK_VREMENA]);
        $predaja = $predavatelj->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        CarbonImmutable::setTestNow($predaja->rok()->istice->addHour());
        $predaja = $predavatelj->ponovi($predaja->refresh());

        $this->assertSame(Stanje::ROK_ISTEKAO, $predaja->stanje);
        $this->assertTrue($predaja->stanje->konacno());
    }

    #[Test]
    public function e1_bez_zapisa_namjere_ne_ostaje_nikakav_trag_predaje(): void
    {
        $predaja = $this->predavatelj([Odgovor::ISTEK_VREMENA], sZapisom: false)
            ->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        $this->assertNull($predaja);
        $this->assertSame(1, Racun::query()->count(), 'Racun je izdan.');
        $this->assertSame(0, Predaja::query()->count(), 'Ali traga o predaji nema.');
    }

    #[Test]
    public function e1_sa_zapisom_namjere_sustav_zna_sto_je_namjeravao_predati(): void
    {
        $this->predavatelj([Odgovor::ISTEK_VREMENA])
            ->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        $nerazrijesene = Predaja::query()
            ->where('stanje', Stanje::PREDANO_NEPOTVRDENO->value)
            ->count();

        $this->assertSame(1, $nerazrijesene);
    }

    #[Test]
    public function b2_s008_uz_ponovni_pokusaj_zavrsava_kao_potvrda(): void
    {
        $predavatelj = $this->predavatelj([Odgovor::ISTEK_VREMENA, Odgovor::S008]);
        $predaja = $predavatelj->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(6));
        $predaja = $predavatelj->ponovi($predaja->refresh());

        $this->assertSame(Stanje::POTVRDENO, $predaja->stanje);
    }

    #[Test]
    public function b2_s008_uz_ispravak_zavrsava_kao_odbijenica(): void
    {
        $predaja = $this->predavatelj([Odgovor::S008])
            ->izdajIPredaj($this->racun(indikatorKopije: true), Namjera::ISPRAVAK);

        $this->assertSame(Stanje::ODBIJEN_ISPRAVAK, $predaja->stanje);
    }

    #[Test]
    public function b2_bez_zapisa_namjere_ista_sifra_ne_razlikuje_dva_ishoda(): void
    {
        $ponovni = $this->predavatelj([Odgovor::S008], sZapisom: false)
            ->izdajIPredaj($this->racun(), Namjera::PONOVNI_POKUSAJ);

        $ispravak = $this->predavatelj([Odgovor::S008], sZapisom: false)
            ->izdajIPredaj($this->racun(indikatorKopije: true), Namjera::ISPRAVAK);

        // Dvije suprotne namjere, ista sifra, isto zavrsno stanje. To je B2.
        $this->assertSame($ponovni->stanje, $ispravak->stanje);
        $this->assertSame(Stanje::PREDANO_NEPOTVRDENO, $ponovni->stanje);
        $this->assertFalse($ponovni->stanje->ishodPoznat());
    }

    #[Test]
    public function evidencija_cuva_i_trenutke_u_kojima_ishod_nije_bio_poznat(): void
    {
        $predaja = $this->predavatelj([Odgovor::ISTEK_VREMENA])
            ->izdajIPredaj($this->racun(), Namjera::PRVO_SLANJE);

        $nejednoznacni = $predaja->dogadjaji()->where('jednoznacno', false)->count();

        $this->assertGreaterThan(0, $nejednoznacni);
    }

    private function predavatelj(array $skripta, bool $sZapisom = true): Predavatelj
    {
        return new Predavatelj(
            new Posrednik($skripta),
            new Automat,
            new TumacOdgovora,
            new RasporedPonavljanja,
            $sZapisom,
        );
    }

    private function racun(bool $indikatorKopije = false): array
    {
        return [
            'broj_dokumenta' => 'R-2026-001',
            'datum_izdavanja' => '2026-09-01',
            'vrsta_dokumenta' => '380',
            'oib_izdavatelja' => '12345678903',
            'vrsta_eracuna' => 'eRacun',
            'indikator_kopije' => $indikatorKopije ?: null,
        ];
    }
}
