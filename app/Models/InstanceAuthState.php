<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class InstanceAuthState extends Model
{
    protected $table = 'instance_auth_states';

    protected $fillable = [
        'instance_id',
        'instance_token',
        'session_data',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }

    /**
     * Store session data with encryption.
     */
    public function setSessionDataAttribute(array|string $data): void
    {
        $json = is_array($data) ? json_encode($data) : $data;
        $this->attributes['session_data'] = Crypt::encryptString($json);
    }

    /**
     * Decrypt session data on access.
     */
    public function getSessionDataAttribute(string $value): array
    {
        try {
            return json_decode(Crypt::decryptString($value), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Find by instance_token (fast path used by internal API).
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('instance_token', $token)->first();
    }
}