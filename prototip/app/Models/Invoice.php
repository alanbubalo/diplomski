<?php

declare(strict_types=1);

namespace App\Models;

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
     * Slozeni identifikator po kojem Sustav za fiskalizaciju provjerava S008.
     *
     * Cetiri polja iz norme uz vrstu eRacuna. Indikator kopije NIJE ovdje, i to
     * je cijeli slucaj B2: propisani ispravak nosi isti identifikator kao
     * izvornik, a polje koje ga cini ispravkom ne ulazi u provjeru.
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
