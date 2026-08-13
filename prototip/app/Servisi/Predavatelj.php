<?php

declare(strict_types=1);

namespace App\Servisi;

use App\Domena\Automat;
use App\Domena\Namjera;
use App\Domena\Odgovor;
use App\Domena\Poticaj;
use App\Domena\RasporedPonavljanja;
use App\Domena\Stanje;
use App\Domena\TumacOdgovora;
use App\Models\Dogadjaj;
use App\Models\Predaja;
use App\Models\Racun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Predaja dokumenta posredniku, u dvije izvedbe koje se razlikuju u jednoj
 * jedinoj stvari: pise li se zapis namjere PRIJE predaje ili POSLIJE odgovora.
 *
 * Ta razlika je cijeli dokazni sadrzaj poglavlja 7. Iz nje slijede sva tri
 * kontrasta koja scenariji pokazuju:
 *
 *   B1  bez zapisa izostanak odgovora ne ostavlja nikakav trag; sa zapisom
 *       dokument sjedi u imenovanom stanju PREDANO_NEPOTVRDENO
 *   E1  bez zapisa poslovni sustav ne moze odgovoriti je li dokument predao
 *   B2  bez zapisa sifra S008 ostaje dvoznacna; sa zapisom je jednoznacna
 *       pri tumacenju
 *
 * Zapis namjere pise se u istoj transakciji kao i sam dokument. Prekid izmedu
 * te dvije radnje inace je najgore mjesto na kojem se dvostruki zapis moze
 * pojaviti: sustav koji je dokument izdao ne bi znao je li ga i predao.
 */
final class Predavatelj
{
    public function __construct(
        private readonly Posrednik $posrednik,
        private readonly Automat $automat,
        private readonly TumacOdgovora $tumac,
        private readonly RasporedPonavljanja $raspored,
        private readonly bool $sZapisomNamjere = true,
    ) {}

    /**
     * Izdavanje dokumenta i predaja u jednom potezu.
     *
     * Vraca null samo u izvedbi bez zapisa namjere, kada odgovor ne stigne.
     * Tada u bazi ne postoji nista, i upravo je to nalaz.
     */
    public function izdajIPredaj(array $podaciRacuna, Namjera $namjera): ?Predaja
    {
        if (! $this->sZapisomNamjere) {
            return $this->bezZapisaNamjere($podaciRacuna, $namjera);
        }

        // Dokument i namjera nastaju zajedno ili nijedno ne nastaje.
        $predaja = DB::transaction(function () use ($podaciRacuna, $namjera): Predaja {
            $racun = Racun::create($podaciRacuna);

            return Predaja::create([
                'racun_id' => $racun->id,
                'kljuc_posiljatelja' => (string) Str::uuid(),
                'namjera' => $namjera,
                'stanje' => Stanje::PRIPREMLJENO,
            ]);
        });

        $this->zabiljezi($predaja, 'zapisana namjera prije predaje', null, $predaja->stanje, true, $namjera->opis());

        return $this->posalji($predaja);
    }

    /**
     * Ponovni pokusaj.
     *
     * Prvo se provjerava rok. Ako je istrosen, ne salje se nista nego se
     * prelazi u ROK_ISTEKAO -- prijelaz koji netko mora pokrenuti, a ne stanje
     * u koje se sklizne protekom vremena.
     */
    public function ponovi(Predaja $predaja): Predaja
    {
        $sada = CarbonImmutable::now();
        $rok = $predaja->rok();

        if ($rok !== null && $rok->istrosen($sada)) {
            return $this->pomakni($predaja, Poticaj::ROK_PROTEKAO, $sada,
                'rok iz cl. 49. st. 1. istrosen prije nego je potvrda stigla');
        }

        $predaja->forceFill(['namjera' => Namjera::PONOVNI_POKUSAJ])->save();

        return $this->posalji($predaja);
    }

    private function posalji(Predaja $predaja): Predaja
    {
        $sada = CarbonImmutable::now();

        $predaja->forceFill(['pokusaj' => $predaja->pokusaj + 1])->save();
        $predaja = $this->pomakni($predaja, Poticaj::PREDANO, $sada,
            sprintf('pokusaj br. %d', $predaja->pokusaj));

        $odgovor = $this->posrednik->predaj(
            $predaja->kljuc_posiljatelja,
            $predaja->racun->slozeniIdentifikator(),
        );

        return $this->primi($predaja, $odgovor, $sada);
    }

    private function primi(Predaja $predaja, Odgovor $odgovor, CarbonImmutable $sada): Predaja
    {
        // Izvedba bez zapisa namjere u trenutku primitka nema sto konzultirati.
        $namjera = $this->sZapisomNamjere ? $predaja->namjera : null;
        $tumacenje = $this->tumac->protumaci($odgovor, $namjera);

        $predaja->forceFill(['zadnji_odgovor' => $odgovor->value])->save();

        if (! $tumacenje->jednoznacno) {
            return $this->ostaniUNeznanju($predaja, $odgovor, $tumacenje->obrazlozenje, $sada);
        }

        return $this->pomakni($predaja, $tumacenje->poticaj, $sada, $tumacenje->obrazlozenje);
    }

    /**
     * Stanje se ne mice. Zapisuje se sto sustav zna i sto ne zna.
     *
     * Ovdje se radi jedina stvar koju izostanak odgovora smije pokrenuti:
     * ako nemogucnost jos nije zabiljezena, biljezi se sada, jer od tog dana
     * tece rok iz cl. 49. st. 1.
     */
    private function ostaniUNeznanju(
        Predaja $predaja,
        Odgovor $odgovor,
        string $obrazlozenje,
        CarbonImmutable $sada,
    ): Predaja {
        if ($odgovor === Odgovor::ISTEK_VREMENA && $predaja->nastanak_nemogucnosti === null) {
            $predaja->forceFill(['nastanak_nemogucnosti' => $sada])->save();
            $predaja->refresh();
        }

        $rok = $predaja->rok();

        $predaja->forceFill([
            'zakljucak' => $obrazlozenje,
            'sljedeci_pokusaj' => $rok !== null
                ? $this->raspored->svjestanRoka($rok, $sada)
                : null,
        ])->save();

        $this->zabiljezi($predaja, 'tumacenje odgovora', $predaja->stanje, $predaja->stanje, false, $obrazlozenje);

        Log::warning('[predaja] ishod nepoznat', [
            'kljuc' => $predaja->kljuc_posiljatelja,
            'odgovor' => $odgovor->value,
            'stanje' => $predaja->stanje->value,
            'obrazlozenje' => $obrazlozenje,
        ]);

        return $predaja;
    }

    private function pomakni(Predaja $predaja, Poticaj $poticaj, CarbonImmutable $sada, string $obrazlozenje): Predaja
    {
        $prije = $predaja->stanje;
        $poslije = $this->automat->prijelaz($prije, $poticaj, $predaja->rok(), $sada);

        $predaja->forceFill([
            'stanje' => $poslije,
            'zakljucak' => $obrazlozenje,
            // Iz konacnog stanja nema prijelaza, pa ni zakazanog pokusaja.
            'sljedeci_pokusaj' => $poslije->konacno() ? null : $predaja->sljedeci_pokusaj,
        ])->save();

        $this->zabiljezi($predaja, $poticaj->value, $prije, $poslije, true, $obrazlozenje);

        Log::info('[predaja] prijelaz', [
            'kljuc' => $predaja->kljuc_posiljatelja,
            'iz' => $prije->value,
            'poticaj' => $poticaj->value,
            'u' => $poslije->value,
        ]);

        return $predaja;
    }

    /** Izvedba u kojoj zapis namjere nastaje tek nakon odgovora. */
    private function bezZapisaNamjere(array $podaciRacuna, Namjera $namjera): ?Predaja
    {
        $racun = Racun::create($podaciRacuna);
        $kljuc = (string) Str::uuid();

        $odgovor = $this->posrednik->predaj($kljuc, $racun->slozeniIdentifikator());

        if ($odgovor === Odgovor::ISTEK_VREMENA) {
            Log::warning('[predaja] odgovor nije stigao, a namjera nije bila zapisana', [
                'racun' => $racun->broj_dokumenta,
            ]);

            // Namjerno se ne zapisuje nista. Poslovni sustav od ovog trenutka
            // ne moze odgovoriti je li dokument predao.
            return null;
        }

        $predaja = Predaja::create([
            'racun_id' => $racun->id,
            'kljuc_posiljatelja' => $kljuc,
            'namjera' => $namjera,
            'stanje' => Stanje::PREDANO_NEPOTVRDENO,
            'pokusaj' => 1,
        ]);

        return $this->primi($predaja, $odgovor, CarbonImmutable::now());
    }

    private function zabiljezi(
        Predaja $predaja,
        string $naziv,
        ?Stanje $prije,
        ?Stanje $poslije,
        bool $jednoznacno,
        string $obrazlozenje,
    ): void {
        Dogadjaj::create([
            'predaja_id' => $predaja->id,
            'redni_broj' => $predaja->dogadjaji()->count() + 1,
            'naziv' => $naziv,
            'stanje_prije' => $prije?->value,
            'stanje_poslije' => $poslije?->value,
            'jednoznacno' => $jednoznacno,
            'obrazlozenje' => $obrazlozenje,
            'nastao_u' => CarbonImmutable::now(),
        ]);
    }
}
