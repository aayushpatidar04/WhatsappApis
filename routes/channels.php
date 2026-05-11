<?php

use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('instance.{token}', function ($user, string $token) {
    $instance = WhatsappInstance::where('instance_token', $token)
        ->whereNull('deleted_at')
        ->first();

    if (!$instance) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    if ($user->isClientAdmin() && $instance->client_id === $user->client_id) {
        return true;
    }

    if ($instance->owner_type === 'user' && $instance->owner_id === $user->id) {
        return true;
    }

    return false;
});

/**
 * Private user notification channel.
 * Used for general user-level notifications (credit low, campaign done, etc.)
 */
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

/**
 * Private client channel.
 * Used for tenant-wide events (new user joined, bulk stats, etc.)
 */
Broadcast::channel('client.{clientId}', function ($user, int $clientId) {
    return $user->client_id === $clientId && $user->isAdminOrAbove();
});
