<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $broj_dokumenta
 * @property CarbonImmutable $datum_izdavanja
 * @property string $vrsta_dokumenta
 * @property string $oib_izdavatelja
 * @property string $vrsta_eracuna
 * @property bool|null $indikator_kopije
 */
final class Racun extends Model
{
    protected $table = 'racuni';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'datum_izdavanja' => 'immutable_date',
            'indikator_kopije' => 'boolean',
        ];
    }

    public function predaje(): HasMany
    {
        return $this->hasMany(Predaja::class);
    }

    /**
     * Slozeni identifikator po kojem Sustav za fiskalizaciju provjerava S008.
     *
     * Cetiri polja iz norme uz vrstu eRacuna. Indikator kopije NIJE ovdje, i
     * to je cijeli slucaj B2: propisani ispravak nosi isti identifikator kao
     * izvornik, a polje koje ga cini ispravkom ne ulazi u provjeru.
     */
    public function slozeniIdentifikator(): string
    {
        return implode('|', [
            $this->oib_izdavatelja,
            $this->broj_dokumenta,
            $this->datum_izdavanja->toDateString(),
            $this->vrsta_dokumenta,
            $this->vrsta_eracuna,
        ]);
    }
}
