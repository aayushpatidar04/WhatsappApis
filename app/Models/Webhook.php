<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'instance_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'failure_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }
    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public static function generateSecret(): string
    {
        return Str::random(40);
    }

    /** Build HMAC-SHA256 signature for a payload */
    public function sign(string $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $this->secret);
    }

    public function scopeActiveFor($q, int $instanceId, string $event)
    {
        return $q->where('is_active', true)
            ->where(fn($q) => $q->where('instance_id', $instanceId)->orWhereNull('instance_id'))
            ->whereJsonContains('events', $event);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}