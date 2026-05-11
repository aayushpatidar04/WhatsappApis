<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'message_id',
        'event',
        'payload',
        'http_status',
        'response_body',
        'attempt',
        'success',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}