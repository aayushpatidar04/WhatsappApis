<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InstanceCredit
 *
 * Represents one month of access for a specific WhatsApp instance.
 * Credits chain: each credit starts when the previous one expires.
 *
 * Lifecycle:
 *   queued   — created, waiting for previous credit to expire
 *   active   — currently running (starts_at <= now <= expires_at)
 *   expired  — expires_at has passed
 *   cancelled — manually cancelled before activation
 */
class InstanceCredit extends Model
{
    protected $table = 'instance_credits';

    protected $fillable = [
        'instance_id',
        'owner_id',
        'owner_type',
        'client_id',
        'credit_order_id',
        'credits',
        'status',
        'starts_at',
        'expires_at',
        'activated_at',
        'expired_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    const STATUS_QUEUED = 'queued';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeForInstance($query, int $instanceId)
    {
        return $query->where('instance_id', $instanceId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status == self::STATUS_ACTIVE;
    }

    public function isQueued(): bool
    {
        return $this->status == self::STATUS_QUEUED;
    }

    public function daysRemaining(): int
    {
        if ($this->status !== self::STATUS_ACTIVE)
            return 0;
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}