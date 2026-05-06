<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'owner_id',
        'owner_type',
        'client_id',
        'type',
        'credits',
        'instance_id',
        'package_id',
        'reference',
        'balance_after',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'credits'       => 'integer',
            'balance_after' => 'integer',
            'created_at'    => 'datetime',
        ];
    }

    const TYPE_PURCHASE          = 'purchase';
    const TYPE_ALLOCATION        = 'allocation';
    const TYPE_DEALLOCATION      = 'deallocation';
    const TYPE_CONSUMPTION       = 'consumption';
    const TYPE_REFUND            = 'refund';
    const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForOwner($query, int $ownerId, string $ownerType)
    {
        return $query->where('owner_id', $ownerId)->where('owner_type', $ownerType);
    }
}