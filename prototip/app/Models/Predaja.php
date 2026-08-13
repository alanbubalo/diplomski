<?php

declare(strict_types=1);

namespace App\Models;

use App\Domena\Namjera;
use App\Domena\Rok;
use App\Domena\Stanje;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $kljuc_posiljatelja
 * @property Namjera $namjera
 * @property Stanje $stanje
 * @property int $pokusaj
 * @property CarbonImmutable|null $nastanak_nemogucnosti
 * @property CarbonImmutable|null $sljedeci_pokusaj
 * @property string|null $zadnji_odgovor
 * @property string|null $zakljucak
 */
final class Predaja extends Model
{
    protected $table = 'predaje';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'namjera' => Namjera::class,
            'stanje' => Stanje::class,
            'nastanak_nemogucnosti' => 'immutable_datetime',
            'sljedeci_pokusaj' => 'immutable_datetime',
        ];
    }

    public function racun(): BelongsTo
    {
        return $this->belongsTo(Racun::class);
    }

    public function dogadjaji(): HasMany
    {
        return $this->hasMany(Dogadjaj::class)->orderBy('redni_broj');
    }

    /**
     * Rok postoji tek kada je nemogucnost nastupila.
     *
     * Dok sve radi, roka iz cl. 49. st. 1. nema jer nema od cega teci.
     */
    public function rok(): ?Rok
    {
        return $this->nastanak_nemogucnosti !== null
            ? Rok::odNastupa($this->nastanak_nemogucnosti)
            : null;
    }
}
