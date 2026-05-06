<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstanceEvent extends Model
{
    public $timestamps  = false;

    protected $fillable = ['instance_id', 'event', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'instance_id');
    }
}