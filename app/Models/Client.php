<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'super_admin_id',
        'max_rate_per_minute',
        'max_instances_per_user',
        'credit_balance',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'credit_balance' => 'integer',
            'max_rate_per_minute' => 'integer',
            'max_instances_per_user' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admin()
    {
        return $this->hasOne(User::class)->where('role', 'client_admin');
    }


    /**
     * WhatsApp instances directly owned by this client (owner_type = 'client').
     * These are instances the Master Admin created for themselves.
     */
    public function ownedInstances(): HasMany
    {
        return $this->hasMany(WhatsappInstance::class, 'owner_id')
            ->where('owner_type', 'client');
    }

    /**
     * ALL instances in this tenant (both user-owned and client-owned).
     */
    public function allInstances(): HasMany
    {
        return $this->hasMany(WhatsappInstance::class, 'client_id');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'owner_id')
            ->where('owner_type', 'client');
    }

    public function creditPackages(): HasMany
    {
        return $this->hasMany(CreditPackage::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasEnoughCredits(int $required): bool
    {
        return $this->credit_balance >= $required;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Slug auto-generation ─────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Client $client) {
            if (empty($client->slug)) {
                $client->slug = Str::slug($client->name) . '-' . Str::random(6);
            }
        });
    }
}