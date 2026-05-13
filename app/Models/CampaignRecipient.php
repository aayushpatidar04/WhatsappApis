<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'message_id',
        'phone',
        'name',
        'variables',
        'status',
        'error_message',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
