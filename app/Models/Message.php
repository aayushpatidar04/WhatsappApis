<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'instance_id',
        'user_id',
        'client_id',
        'campaign_id',
        'direction',
        'wa_message_id',
        'recipient_jid',
        'type',
        'body',
        'media_url',
        'media_mime',
        'media_filename',
        'metadata',
        'status',
        'error_message',
        'retry_count',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    // Status constants
    const STATUS_QUEUED = 'queued';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';
    const STATUS_REJECTED = 'rejected';

    // Baileys ACK numeric → our status
    const ACK_MAP = [
        0 => self::STATUS_QUEUED,
        1 => self::STATUS_SENT,       // server ack
        2 => self::STATUS_DELIVERED,  // device ack
        3 => self::STATUS_READ,       // read ack
        4 => self::STATUS_READ,       // played (audio)
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Formatted recipient (strips @s.whatsapp.net)
    public function getPhoneAttribute(): ?string
    {
        $meta = $this->metadata ?? [];

        // Prefer senderPn for @lid cases
        $jid = $meta['key']['senderPn']
            ?? $meta['key']['participantPn']
            ?? $this->recipient_jid;

        if (!$jid) {
            return null;
        }

        // Strip suffix
        $digits = str_replace(['@s.whatsapp.net', '@lid', '@g.us'], '', $jid);

        // Normalize: 12 digits starting with 91 (India), else 10 digits
        $phone = null;
        if (preg_match('/^91\d{10}$/', $digits)) {
            $phone = $digits; // full 12‑digit Indian number
        } elseif (preg_match('/^\d{10}$/', $digits)) {
            $phone = $digits; // plain 10‑digit number
        } else {
            $phone = $digits; // fallback
        }

        // Append pushName if available
        $pushName = $meta['pushName'] ?? null;
        if ($pushName) {
            return $phone . ' (' . $pushName . ')';
        }

        return $phone;
    }


    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }
    public function scopeForInstance($q, int $id)
    {
        return $q->where('instance_id', $id);
    }
    public function scopeInbound($q)
    {
        return $q->where('direction', 'inbound');
    }
    public function scopeOutbound($q)
    {
        return $q->where('direction', 'outbound');
    }
}