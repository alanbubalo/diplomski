<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $redni_broj
 * @property string $naziv
 * @property string|null $stanje_prije
 * @property string|null $stanje_poslije
 * @property bool $jednoznacno
 * @property string|null $obrazlozenje
 * @property CarbonImmutable $nastao_u
 */
final class Dogadjaj extends Model
{
    protected $table = 'dogadjaji';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'jednoznacno' => 'boolean',
            'nastao_u' => 'immutable_datetime',
        ];
    }

    public function predaja(): BelongsTo
    {
        return $this->belongsTo(Predaja::class);
    }
}
