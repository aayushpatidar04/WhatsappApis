<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_hash',
        'name',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function markUsed(): void
    {
        $this->updateQuietly(['last_used_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    // ─── Static factory ───────────────────────────────────────────────────────

    /**
     * Generate a new token, store its hash, return the plain token (shown once only).
     */
    public static function generate(int $userId, string $name, ?\Carbon\Carbon $expiresAt = null): array
    {
        $plain = 'wat_' . Str::random(60); // wat_ prefix = WhatsApp Token
        $hash  = hash('sha256', $plain);

        $token = self::create([
            'user_id'    => $userId,
            'token_hash' => $hash,
            'name'       => $name,
            'expires_at' => $expiresAt,
            'is_active'  => true,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * Find a token record by the plain token string.
     */
    public static function findByPlain(string $plain): ?self
    {
        $hash = hash('sha256', $plain);

        return self::where('token_hash', $hash)->first();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}