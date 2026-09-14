<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Intent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $document_number
 * @property CarbonImmutable $issue_date
 * @property string $document_type
 * @property string $issuer_oib
 * @property string $einvoice_type
 * @property bool|null $copy_indicator
 */
final class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date',
            'copy_indicator' => 'boolean',
        ];
    }

        public function handovers(): HasMany
    {
        return $this->hasMany(Handover::class);
    }

    /**
     * Poslovna namjera koliko se moze izvesti iz samog dokumenta. Indikator
     * kopije je polje eRacuna (HR-BT-1), pa ga ima i izvedba koja namjeru nije
     * zapisala. Redni pokusaj dostave se iz dokumenta ne moze izvesti; on zivi
     * samo u zapisu predaje (vidi ResponseInterpreter i odjeljak 7.3 rada).
     */
    public function derivedIntent(): Intent
    {
        return $this->copy_indicator === true ? Intent::CORRECTION : Intent::ORIGINAL;
    }

    /**
     * Slozeni identifikator po kojem Sustav za fiskalizaciju provjerava S008:
     * cetiri polja iz norme uz vrstu eRacuna. Indikator kopije nije medu njima,
     * pa propisani ispravak nosi isti identifikator kao izvornik (slucaj B2).
     */
    public function compositeIdentifier(): string
    {
        return implode('|', [
            $this->issuer_oib,
            $this->document_number,
            $this->issue_date->toDateString(),
            $this->document_type,
            $this->einvoice_type,
        ]);
    }
}
