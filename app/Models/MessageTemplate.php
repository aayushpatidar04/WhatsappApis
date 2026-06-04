<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'name', 'category', 'body',
        'media_url', 'media_type', 'variables',
        'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }

    public function scopeForClient($q, int $clientId) { return $q->where('client_id', $clientId); }
}