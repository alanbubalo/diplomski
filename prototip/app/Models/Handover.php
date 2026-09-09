<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deadline;
use App\Domain\Intent;
use App\Domain\State;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $sender_key
 * @property Intent|null $intent
 * @property State $state
 * @property int $attempt
 * @property CarbonImmutable|null $impossibility_onset_at
 * @property CarbonImmutable|null $next_attempt_at
 * @property string|null $last_response
 * @property string|null $conclusion
 */
final class Handover extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'intent' => Intent::class,
            'state' => State::class,
            'impossibility_onset_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }

    /** Je li ovo ponovljena dostava iste poruke, a ne prva. */
    public function isRepeatDelivery(): bool
    {
        return $this->attempt > 1;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditEntry::class)->orderBy('sequence');
    }

    /**
     * Rok postoji tek kada je nemogucnost nastupila.
     *
     * Dok sve radi, roka iz cl. 49. st. 1. nema jer nema od cega teci.
     */
    public function deadline(): ?Deadline
    {
        return $this->impossibility_onset_at !== null
            ? Deadline::fromOnset($this->impossibility_onset_at)
            : null;
    }
}
