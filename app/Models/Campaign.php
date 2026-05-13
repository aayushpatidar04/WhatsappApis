<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'instance_id',
        'contact_group_id',
        'name',
        'status',
        'message_type',
        'message_payload',
        'schedule_time',
        'send_window_start',
        'send_window_end',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'message_payload' => 'array',
            'schedule_time' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }
    public function contactGroup(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id');
    }
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function progressPct(): float
    {
        if (!$this->total_recipients)
            return 0;
        return round((($this->sent_count + $this->failed_count) / $this->total_recipients) * 100, 1);
    }

    public function deliveryRate(): float
    {
        if (!$this->sent_count)
            return 0;
        return round(($this->delivered_count / $this->sent_count) * 100, 1);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }
}
