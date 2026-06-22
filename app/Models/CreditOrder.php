<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditOrder extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'package_id',
        'order_number',
        'credits',
        'amount',
        'currency',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'status',
        'failure_reason',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credits' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'package_id');
    }

    public static function generateOrderNumber(): string
    {
        $year = now()->format('Y');
        $seq = static::whereYear('created_at', $year)->count() + 1;
        return "WAP-{$year}-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function isPaid(): bool
    {
        return $this->status == self::STATUS_PAID;
    }
    public function isPending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
}