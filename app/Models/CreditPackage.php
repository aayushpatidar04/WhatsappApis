<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'credits',
        'price',
        'currency',
        'validity_days',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credits'       => 'integer',
            'price'         => 'decimal:2',
            'validity_days' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}