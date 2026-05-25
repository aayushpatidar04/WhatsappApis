<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappInstance;

class MessageLimit extends Model
{
    public const OWNER_TYPE_USER = 'user';
    public const OWNER_TYPE_CLIENT = 'client';
    public const OWNER_TYPE_INSTANCE = 'instance';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'instance_id',
        'max_per_minute',
    ];

    protected $casts = [
        'owner_type' => 'string',
        'max_per_minute' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }

    public function resolveOwner(): User|Client|WhatsappInstance|null
    {
        return match ($this->owner_type) {
            self::OWNER_TYPE_USER   => User::find($this->owner_id),
            self::OWNER_TYPE_CLIENT => Client::find($this->owner_id),
            self::OWNER_TYPE_INSTANCE => $this->instance,
            default => null,
        };
    }

    public function isInstanceOverride(): bool
    {
        return $this->owner_type === self::OWNER_TYPE_INSTANCE && $this->instance_id !== null;
    }

    public function isOwnerOverride(): bool
    {
        return in_array($this->owner_type, [self::OWNER_TYPE_USER, self::OWNER_TYPE_CLIENT], true);
    }

    public function isGlobalDefault(): bool
    {
        return $this->owner_id === null && $this->owner_type === null && $this->instance_id === null;
    }

    public function scopeForOwner($query, int $ownerId, string $ownerType)
    {
        return $query->where('owner_id', $ownerId)
            ->where('owner_type', $ownerType)
            ->whereNull('instance_id');
    }

    public function scopeForInstance($query, int $instanceId)
    {
        return $query->where('instance_id', $instanceId)
            ->where('owner_type', self::OWNER_TYPE_INSTANCE);
    }

    public function scopeGlobalDefault($query)
    {
        return $query->whereNull('owner_id')
            ->whereNull('owner_type')
            ->whereNull('instance_id');
    }
}
