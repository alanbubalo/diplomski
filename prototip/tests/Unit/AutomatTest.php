<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domena\Automat;
use App\Domena\Poticaj;
use App\Domena\Rok;
use App\Domena\Stanje;
use Carbon\CarbonImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tvrdnje o automatu, pisane u uvjetnom obliku koji metoda iz poglavlja 8
 * trazi: ako nastupi F, uz obrazac O zavrsno stanje mora biti S.
 */
final class AutomatTest extends TestCase
{
    private Automat $automat;

    private CarbonImmutable $sada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->automat = new Automat;
        $this->sada = CarbonImmutable::parse('2026-09-01 09:00:00');
    }

    #[Test]
    public function predaja_bez_nastupa_nemogucnosti_ne_provjerava_rok(): void
    {
        $novo = $this->automat->prijelaz(
            Stanje::PRIPREMLJENO,
            Poticaj::PREDANO,
            null,
            $this->sada,
        );

        $this->assertSame(Stanje::PREDANO_NEPOTVRDENO, $novo);
    }

    #[Test]
    public function izostanak_odgovora_ne_pomice_stanje(): void
    {
        // Nema poticaja koji bi odgovarao isteku vremena. To je namjerno:
        // dokument koji je predan i nije potvrden ostaje predan i nepotvrden.
        $this->assertFalse(
            $this->automat->dopusten(Stanje::PREDANO_NEPOTVRDENO, Poticaj::POTVRDA_IZ_S008)
            && $this->automat->dopusten(Stanje::POTVRDENO, Poticaj::PREDANO),
        );
    }

    #[Test]
    public function iz_konacnog_stanja_nema_prijelaza(): void
    {
        $this->expectException(DomainException::class);

        $this->automat->prijelaz(Stanje::POTVRDENO, Poticaj::PREDANO, null, $this->sada);
    }

    #[Test]
    public function ponovna_predaja_nije_dopustena_nakon_isteka_roka(): void
    {
        $rok = Rok::odNastupa($this->sada);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cl. 49. st. 1.');

        $this->automat->prijelaz(
            Stanje::PREDANO_NEPOTVRDENO,
            Poticaj::PREDANO,
            $rok,
            $rok->istice->addSecond(),
        );
    }

    #[Test]
    public function u_rok_istekao_se_ne_moze_uci_dok_rok_jos_tece(): void
    {
        $rok = Rok::odNastupa($this->sada);

        $this->expectException(DomainException::class);

        $this->automat->prijelaz(
            Stanje::PREDANO_NEPOTVRDENO,
            Poticaj::ROK_PROTEKAO,
            $rok,
            $this->sada->addDay(),
        );
    }

    #[Test]
    public function pokusaj_nakon_isteka_roka_vodi_u_rok_istekao(): void
    {
        $rok = Rok::odNastupa($this->sada);

        $novo = $this->automat->prijelaz(
            Stanje::PREDANO_NEPOTVRDENO,
            Poticaj::ROK_PROTEKAO,
            $rok,
            $rok->istice->addSecond(),
        );

        $this->assertSame(Stanje::ROK_ISTEKAO, $novo);
        $this->assertTrue($novo->konacno());
    }
}
