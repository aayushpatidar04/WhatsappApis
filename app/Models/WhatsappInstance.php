<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WhatsappInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'client_id',
        'name',
        'phone_number',
        'instance_token',
        'status',
        'credits_assigned',
        'credits_consumed',
        'activated_at',
        'expires_at',
        'suspended_at',
        'last_connected_at',
        'session_data',
        'reconnect_attempts',
        'webhook_url',
    ];

    protected $hidden = [
        'session_data', // Never leak session data via API
    ];

    protected function casts(): array
    {
        return [
            'credits_assigned'   => 'integer',
            'credits_consumed'   => 'decimal:4',
            'activated_at'       => 'datetime',
            'expires_at'         => 'datetime',
            'suspended_at'       => 'datetime',
            'last_connected_at'  => 'datetime',
            'reconnect_attempts' => 'integer',
        ];
    }

    // ─── Status constants ─────────────────────────────────────────────────────

    const STATUS_PENDING      = 'pending';
    const STATUS_ACTIVE       = 'active';
    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_SUSPENDED    = 'suspended';
    const STATUS_EXPIRED      = 'expired';

    const OWNER_TYPE_USER        = 'user';
    const OWNER_TYPE_CLIENT      = 'client';
    const OWNER_TYPE_SUPER_ADMIN = 'super_admin';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Resolve the owner model dynamically based on owner_type.
     * For 'user' and 'super_admin' owner_type, resolves to User.
     * For 'client' owner_type, resolves to Client.
     */
    public function owner(): User|Client|null
    {
        return match ($this->owner_type) {
            self::OWNER_TYPE_CLIENT => Client::find($this->owner_id),
            default                 => User::find($this->owner_id),
        };
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'instance_id');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConnectable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_DISCONNECTED]);
    }

    public function isSendable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function creditsRemaining(): int
    {
        return max(0, $this->credits_assigned - (int) ceil($this->credits_consumed));
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeOwnedBy($query, int $ownerId, string $ownerType)
    {
        return $query->where('owner_id', $ownerId)->where('owner_type', $ownerType);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    // ─── Token generation ─────────────────────────────────────────────────────

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(40));
        } while (self::where('instance_token', $token)->exists());

        return $token;
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WhatsappInstance $instance) {
            if (empty($instance->instance_token)) {
                $instance->instance_token = self::generateToken();
            }
        });
    }
}