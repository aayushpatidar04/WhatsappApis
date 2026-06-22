<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly BaileysClient $baileys)
    {
    }

    // ─── End User Dashboard ───────────────────────────────────────────────────

    public function userDashboard(): Response
    {
        $user = Auth::user();

        $instances = WhatsappInstance::ownedBy($user->id, 'user')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id',
                'name',
                'phone_number',
                'status',
                'credits_assigned',
                'credits_consumed',
                'expires_at',
                'activated_at'
            ]);

        $msgBase = Message::where('user_id', $user->id);

        $stats = [
            'total_instances' => WhatsappInstance::ownedBy($user->id, 'user')->whereNull('deleted_at')->count(),
            'active_instances' => WhatsappInstance::ownedBy($user->id, 'user')->where('status', 'active')->count(),
            'messages_today' => (clone $msgBase)->whereDate('created_at', today())->outbound()->count(),
            'messages_received' => (clone $msgBase)->whereDate('created_at', today())->inbound()->count(),
            'delivery_rate' => $this->deliveryRate($msgBase),
            'failed_today' => (clone $msgBase)->whereDate('created_at', today())->where('status', 'failed')->count(),
        ];

        return Inertia::render('User/Dashboard', compact('stats', 'instances'));
    }

    public function userInstances(): Response
    {
        $user = Auth::user();

        $instances = WhatsappInstance::ownedBy($user->id, 'user')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->paginate(12);

        // Transform for frontend
        $instances->through(fn($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'phone_number' => $i->phone_number,
            'instance_token' => $i->instance_token,
            'status' => $i->status,
            'owner_type' => $i->owner_type,
            'credits_assigned' => $i->credits_assigned,
            'credits_remaining' => $i->creditsRemaining(),
            'days_until_expiry' => $i->daysUntilExpiry(),
            'expires_at' => $i->expires_at?->toIso8601String(),
            'activated_at' => $i->activated_at?->toIso8601String(),
            'created_at' => $i->created_at->toIso8601String(),
            'webhook_url' => $i->webhook_url,
            'credits_consumed' => (float) $i->credits_consumed,
            'reconnect_attempts' => $i->reconnect_attempts,
            'last_connected_at' => $i->last_connected_at?->toIso8601String(),
            'is_own' => true,
        ]);

        return Inertia::render('User/Instances', compact('instances'));
    }

    public function userTokens(): Response
    {
        $tokens = Auth::user()
            ->apiTokens()
            ->active()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return Inertia::render('User/Tokens', compact('tokens'));
    }

    // ─── Client Admin Dashboard ───────────────────────────────────────────────

    public function clientDashboard(): Response
    {
        $user = Auth::user();
        $client = $user->client;

        $allInstances = WhatsappInstance::where('client_id', $client->id)
            ->whereNull('deleted_at');

        $msgBase = Message::where('client_id', $client->id);

        $ownInstances = WhatsappInstance::ownedBy($client->id, 'client')
            ->whereNull('deleted_at');

        $stats = [
            'client_credit_balance' => $client->credit_balance,
            'total_users' => User::where('client_id', $client->id)->where('role', 'user')->count(),
            'active_instances' => (clone $allInstances)->where('status', 'active')->count(),
            'pending_instances' => (clone $allInstances)->whereIn('status', ['pending', 'disconnected'])->count(),
            'suspended_instances' => (clone $allInstances)->whereIn('status', ['suspended', 'expired'])->count(),
            'own_instances' => WhatsappInstance::ownedBy($client->id, 'client')->whereNull('deleted_at')->count(),
            'messages_today' => (clone $msgBase)->whereDate('created_at', today())->outbound()->count(),
            'messages_received' => (clone $msgBase)->whereDate('created_at', today())->inbound()->count(),
            'delivery_rate' => $this->deliveryRate($msgBase),
        ];

        $recentUsers = User::where('client_id', $client->id)
            ->where('role', 'user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'credit_balance']);

        return Inertia::render('Client/Dashboard', compact('stats', 'recentUsers'));
    }

    public function clientInstances(): Response
    {
        $user = Auth::user();
        $client = $user->client;

        // Client admin sees ALL instances in the tenant (both client-owned and user-owned)
        $instances = WhatsappInstance::where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->with('client:id,name')
            ->orderBy('owner_type') // client-owned first
            ->orderByDesc('created_at')
            ->paginate(12);

        $instances->through(fn($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'phone_number' => $i->phone_number,
            'instance_token' => $i->instance_token,
            'status' => $i->status,
            'owner_type' => $i->owner_type,
            'owner_id' => $i->owner_id,
            'credits_assigned' => $i->credits_assigned,
            'credits_remaining' => $i->creditsRemaining(),
            'days_until_expiry' => $i->daysUntilExpiry(),
            'expires_at' => $i->expires_at?->toIso8601String(),
            'activated_at' => $i->activated_at?->toIso8601String(),
            'created_at' => $i->created_at->toIso8601String(),
            'webhook_url' => $i->webhook_url,
            'is_own' => $i->owner_type == 'client' && $i->owner_id == $client->id,
        ]);

        return Inertia::render('Client/Instances', compact('instances'));
    }

    // ─── Super Admin Dashboard ────────────────────────────────────────────────

    public function superDashboard(): Response
    {
        $stats = [
            'total_clients' => Client::count(),
            'total_users' => User::where('role', 'user')->count(),
            'active_instances' => WhatsappInstance::where('status', 'active')->count(),
            'messages_today' => Message::whereDate('created_at', today())->outbound()->count(),
            'credits_sold' => 0, // Phase 5
        ];

        $recentClients = Client::withCount(['users', 'allInstances'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $health = [
            'baileys' => $this->baileys->health(),
            'queue_depth' => DB::table('jobs')->count(),
        ];

        return Inertia::render('Admin/Dashboard', compact('stats', 'recentClients', 'health'));
    }


    // ── Helpers ───────────────────────────────────────────────────────────────

    private function deliveryRate($query): float
    {
        $total = (clone $query)->outbound()->whereNotNull('sent_at')->count();
        $delivered = (clone $query)->outbound()->whereIn('status', ['delivered', 'read'])->count();
        return $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
    }
}