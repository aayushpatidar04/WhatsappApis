<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MessageLimit;
use App\Models\WhatsappInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RateLimitController extends Controller
{
    /**
     * GET /client/rate-limits  (Inertia page)
     */
    public function page(): Response
    {
        $user   = Auth::user();
        $client = $user->client;

        // All users in this tenant with their current limits
        $users = \App\Models\User::where('client_id', $client->id)
            ->where('role', 'user')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'limit' => $this->getLimit('user', $u->id),
            ]);

        // All instances in tenant with their limits
        $instances = WhatsappInstance::where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'phone_number', 'owner_type', 'owner_id'])
            ->map(fn($i) => [
                'id'          => $i->id,
                'name'        => $i->name,
                'phone'       => $i->phone_number,
                'owner_type'  => $i->owner_type,
                'limit'       => $this->getLimit('instance', $i->id),
            ]);

        return Inertia::render('Client/RateLimits', [
            'client_limit' => $client->max_rate_per_minute,
            'users'        => $users,
            'instances'    => $instances,
            'min_rate'     => (int) config('app.min_messages_per_minute', 5),
            'max_rate'     => (int) $client->max_rate_per_minute,
        ]);
    }

    /**
     * PUT /client/rate-limits/client
     * Set client-level default rate (applies to all users without override).
     */
    public function setClientRate(Request $request): JsonResponse
    {
        $user   = Auth::user();
        $client = $user->client;

        $validated = $request->validate([
            'max_per_minute' => ['required', 'integer', 'min:5', 'max:20'],
        ]);

        // Update client record's max_rate_per_minute (this IS the default)
        $client->update(['max_rate_per_minute' => $validated['max_per_minute']]);

        return response()->json([
            'success' => true,
            'message' => "Client default rate updated to {$validated['max_per_minute']}/min.",
        ]);
    }

    /**
     * PUT /client/rate-limits/user/{userId}
     * Set per-user rate limit override.
     */
    public function setUserRate(Request $request, int $userId): JsonResponse
    {
        $user   = Auth::user();
        $client = $user->client;

        // Ensure target user belongs to this client
        $targetUser = \App\Models\User::where('id', $userId)
            ->where('client_id', $client->id)
            ->firstOrFail();

        $validated = $request->validate([
            'max_per_minute' => ['required', 'integer', 'min:5', 'max:' . $client->max_rate_per_minute],
        ]);

        DB::table('message_limits')->updateOrInsert(
            ['owner_id' => $userId, 'owner_type' => 'user', 'instance_id' => null],
            ['max_per_minute' => $validated['max_per_minute'], 'updated_at' => now(), 'created_at' => now()],
        );

        return response()->json([
            'success' => true,
            'message' => "Rate for {$targetUser->name} set to {$validated['max_per_minute']}/min.",
            'data'    => ['user_id' => $userId, 'max_per_minute' => $validated['max_per_minute']],
        ]);
    }

    /**
     * PUT /client/rate-limits/instance/{instanceId}
     * Set per-instance rate limit override.
     */
    public function setInstanceRate(Request $request, int $instanceId): JsonResponse
    {
        $user     = Auth::user();
        $client   = $user->client;
        $instance = WhatsappInstance::where('id', $instanceId)
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $validated = $request->validate([
            'max_per_minute' => ['required', 'integer', 'min:5', 'max:' . $client->max_rate_per_minute],
        ]);

        DB::table('message_limits')->updateOrInsert(
            ['instance_id' => $instanceId, 'owner_type' => 'instance'],
            ['owner_id' => $instanceId, 'max_per_minute' => $validated['max_per_minute'], 'updated_at' => now(), 'created_at' => now()],
        );

        // Clear cached rate so queue workers pick it up immediately
        \Illuminate\Support\Facades\Cache::forget("rate_last_sent:{$instanceId}");

        return response()->json([
            'success' => true,
            'message' => "Rate for {$instance->name} set to {$validated['max_per_minute']}/min.",
            'data'    => ['instance_id' => $instanceId, 'max_per_minute' => $validated['max_per_minute']],
        ]);
    }

    /**
     * DELETE /client/rate-limits/user/{userId}
     * Remove user override (revert to client default).
     */
    public function resetUserRate(int $userId): JsonResponse
    {
        DB::table('message_limits')
            ->where('owner_id', $userId)
            ->where('owner_type', 'user')
            ->whereNull('instance_id')
            ->delete();

        return response()->json(['success' => true, 'message' => 'Override removed. User reverts to client default.']);
    }

    /**
     * DELETE /client/rate-limits/instance/{instanceId}
     * Remove instance override.
     */
    public function resetInstanceRate(int $instanceId): JsonResponse
    {
        DB::table('message_limits')
            ->where('instance_id', $instanceId)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Override removed. Instance reverts to user/client default.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getLimit(string $type, int $id): int
    {
        if ($type == 'instance') {
            return (int) (MessageLimit::forInstance($id)->value('max_per_minute')
                ?? Auth::user()->client->max_rate_per_minute);
        }

        return (int) (MessageLimit::forOwner($id, 'user')->value('max_per_minute')
            ?? Auth::user()->client->max_rate_per_minute);
    }
}