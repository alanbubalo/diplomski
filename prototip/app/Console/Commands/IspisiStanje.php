<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dogadjaj;
use App\Models\Predaja;
use App\Models\Racun;
use Illuminate\Console\Command;

/**
 * Ispis zavrsnog stanja baze nakon scenarija.
 *
 * Ovo je prva polovica dokaznog materijala poglavlja 7; druga je isjecak
 * dnevnika. Ispis se namjerno ne uljepsava: pokazuje redke onako kako stoje,
 * ukljucujuci dogadaje ciji zakljucak nije bio jednoznacan.
 */
final class IspisiStanje extends Command
{
    protected $signature = 'stanje';

    protected $description = 'Ispisuje zavrsno stanje racuna, predaja i evidencije';

    public function handle(): int
    {
        $this->newLine();

        $this->line('  RACUNI');
        $this->table(
            ['id', 'broj', 'datum', 'vrsta', 'ind. kopije'],
            Racun::query()->get()->map(fn (Racun $r): array => [
                (string) $r->id,
                $r->broj_dokumenta,
                $r->datum_izdavanja->toDateString(),
                $r->vrsta_dokumenta,
                $r->indikator_kopije === null ? '-' : ($r->indikator_kopije ? 'true' : 'false'),
            ])->all(),
        );

        $this->line('  PREDAJE');
        $this->table(
            ['id', 'racun', 'namjera', 'stanje', 'pok.', 'odgovor', 'ishod poznat'],
            Predaja::query()->with('racun')->get()->map(fn (Predaja $p): array => [
                (string) $p->id,
                $p->racun->broj_dokumenta,
                $p->namjera->value,
                $p->stanje->value,
                (string) $p->pokusaj,
                $p->zadnji_odgovor ?? '-',
                $p->stanje->ishodPoznat() ? 'da' : 'ne',
            ])->all(),
        );

        $this->line('  EVIDENCIJA');
        $this->table(
            ['predaja', 'br.', 'dogadaj', 'iz', 'u', 'jednoznacno'],
            Dogadjaj::query()->orderBy('predaja_id')->orderBy('redni_broj')->get()
                ->map(fn (Dogadjaj $d): array => [
                    (string) $d->predaja_id,
                    (string) $d->redni_broj,
                    $d->naziv,
                    $d->stanje_prije ?? '-',
                    $d->stanje_poslije ?? '-',
                    $d->jednoznacno ? 'da' : 'NE',
                ])->all(),
        );

        $nejednoznacnih = Dogadjaj::query()->where('jednoznacno', false)->count();

        $this->line(sprintf(
            '  Zapisanih trenutaka u kojima sustav nije znao ishod: %d.',
            $nejednoznacnih,
        ));
        $this->newLine();

        return self::SUCCESS;
    }
}
