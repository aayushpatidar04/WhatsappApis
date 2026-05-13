<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'user_id',
        'name',
        'phone',
        'email',
        'tags',
        'custom_fields',
        'is_whatsapp',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom_fields' => 'array',
            'is_whatsapp' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_members', 'contact_id', 'group_id');
    }

    // Normalise phone to E.164-ish (digits only, strip leading +)
    public static function normalisePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    public function scopeForClient($q, int $clientId)
    {
        return $q->where('client_id', $clientId);
    }
    public function scopeActive($q)
    {
        return $q->where('is_blocked', false);
    }
    public function scopeWithTag($q, string $tag)
    {
        return $q->whereJsonContains('tags', $tag);
    }
}