<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $sequence
 * @property string $name
 * @property string|null $state_before
 * @property string|null $state_after
 * @property bool $unambiguous
 * @property string|null $reason
 * @property CarbonImmutable $occurred_at
 */
final class AuditEntry extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'unambiguous' => 'boolean',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }
}
