<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Record an audit event. Call from controllers/services.
     */
    public static function record(
        string  $event,
        mixed   $auditable   = null,
        array   $oldValues   = [],
        array   $newValues   = [],
        ?int    $userId      = null,
    ): void {
        static::create([
            'user_id'         => $userId ?? Auth::id(),
            'event'           => $event,
            'auditable_type'  => $auditable ? class_basename($auditable) : null,
            'auditable_id'    => $auditable?->id,
            'old_values'      => $oldValues ?: null,
            'new_values'      => $newValues ?: null,
            'ip_address'      => request()->ip(),
            'user_agent'      => substr(request()->userAgent() ?? '', 0, 255),
            'created_at'      => now(),
        ]);
    }
}