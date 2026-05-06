<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'email',
        'password',
        'role',
        'credit_balance',
        'phone',
        'timezone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'credit_balance'    => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * WhatsApp instances directly owned by this user (owner_type = 'user').
     */
    public function instances(): HasMany
    {
        return $this->hasMany(WhatsappInstance::class, 'owner_id')
            ->where('owner_type', 'user');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'owner_id')
            ->where('owner_type', 'user');
    }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isClientAdmin(): bool
    {
        return $this->role === 'client_admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAdminOrAbove(): bool
    {
        return in_array($this->role, ['super_admin', 'client_admin']);
    }

    // ─── Tenant helpers ───────────────────────────────────────────────────────

    /**
     * Does this user belong to the same client as the given client_id?
     */
    public function belongsToClient(int $clientId): bool
    {
        return $this->client_id === $clientId;
    }

    // ─── Credit helpers ───────────────────────────────────────────────────────

    public function hasEnoughCredits(int $required): bool
    {
        return $this->credit_balance >= $required;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}