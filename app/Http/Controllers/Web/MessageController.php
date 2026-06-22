<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\WhatsappInstance;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messageService) {}

    // ── Inertia pages ─────────────────────────────────────────────────────────

    public function sendPage(): Response
    {
        $user      = Auth::user();
        $instances = WhatsappInstance::where('status', 'active')
            ->whereNull('deleted_at')
            ->when($user->isClientAdmin(), fn($q) => $q->where('client_id', $user->client_id))
            ->when($user->isUser(), fn($q) => $q->where('owner_id', $user->id)->where('owner_type', 'user'))
            ->get(['id', 'name', 'phone_number', 'instance_token']);

        return Inertia::render('User/Send', compact('instances'));
    }

    public function inboxPage(): Response
    {
        return Inertia::render('User/Inbox');
    }

    // ── JSON API: Send ────────────────────────────────────────────────────────

    /**
     * POST /dashboard/messages/send
     * Send a message from the dashboard quick-send form.
     * Uses session auth. Queues the job with rate limiting.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => ['required', 'integer'],
            'to'          => ['required', 'string', 'max:60'],
            'type'        => ['required', 'in:text,image,video,audio,document,location,poll'],
            'message'     => ['required_if:type,text', 'string', 'max:4096'],
            'media_url'   => ['required_unless:type,text,location,poll', 'url', 'max:500'],
            'caption'     => ['sometimes', 'string', 'max:1024'],
            'filename'    => ['sometimes', 'string', 'max:255'],
            'latitude'    => ['required_if:type,location', 'numeric'],
            'longitude'   => ['required_if:type,location', 'numeric'],
            'question'    => ['required_if:type,poll', 'string'],
            'options'     => ['required_if:type,poll', 'array', 'min:2', 'max:12'],
        ]);

        $instance = $this->resolveInstance($validated['instance_id']);

        if (!$instance->isSendable()) {
            return response()->json([
                'success' => false,
                'message' => "Instance is not ready. Status: {$instance->status}.",
            ], 422);
        }

        $message = $this->messageService->dispatch(
            instance: $instance,
            user:     Auth::user(),
            payload:  $validated,
            priority: 'high',   // Dashboard sends get high priority queue
        );

        return response()->json([
            'success'    => true,
            'message_id' => $message->id,
            'status'     => $message->status,
        ], 202);
    }

    // ── JSON API: Message Log ─────────────────────────────────────────────────

    /**
     * GET /dashboard/messages
     * Paginated message log with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Message::with('instance:id,name,phone_number')
            ->orderByDesc('created_at');

        // Scope by role
        if ($user->isSuperAdmin()) {
            if ($request->filled('client_id')) $query->where('client_id', $request->integer('client_id'));
        } elseif ($user->isClientAdmin()) {
            $query->where('client_id', $user->client_id);
        } else {
            $query->where('user_id', $user->id);
        }

        // Filters
        if ($request->filled('instance_id')) $query->where('instance_id', $request->integer('instance_id'));
        if ($request->filled('direction'))   $query->where('direction', $request->direction);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('type'))        $query->where('type', $request->type);
        if ($request->filled('search')) {
            $query->where(fn($q) => $q
                ->where('body', 'like', "%{$request->search}%")
                ->orWhere('recipient_jid', 'like', "%{$request->search}%")
            );
        }
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('created_at', '<=', $request->to);

        $messages = $query->paginate($request->integer('per_page', 25))
            ->through(fn($m) => $this->messageService->formatMessage($m));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * GET /dashboard/messages/inbox
     * Inbound-only messages for unified inbox view.
     */
    public function inbox(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Message::inbound()
            ->with('instance:id,name,phone_number')
            ->orderByDesc('created_at');

        if ($user->isClientAdmin()) $query->where('client_id', $user->client_id);
        else $query->where('user_id', $user->id);

        if ($request->filled('instance_id')) $query->where('instance_id', $request->integer('instance_id'));
        if ($request->filled('search')) {
            $query->where(fn($q) => $q
                ->where('body', 'like', "%{$request->search}%")
                ->orWhere('recipient_jid', 'like', "%{$request->search}%")
            );
        }

        $messages = $query->paginate($request->integer('per_page', 30))
            ->through(fn($m) => $this->messageService->formatMessage($m));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * GET /dashboard/messages/stats
     * Message stats for dashboard overview widget.
     */
    public function stats(): JsonResponse
    {
        $user  = Auth::user();
        $base  = Message::where(
            $user->isClientAdmin() ? 'client_id' : 'user_id',
            $user->isClientAdmin() ? $user->client_id : $user->id
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'today_sent'      => (clone $base)->outbound()->whereDate('created_at', today())->count(),
                'today_received'  => (clone $base)->inbound()->whereDate('created_at', today())->count(),
                'week_sent'       => (clone $base)->outbound()->whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
                'delivery_rate'   => $this->deliveryRate(clone $base),
                'failed_today'    => (clone $base)->where('status', 'failed')->whereDate('created_at', today())->count(),
            ],
        ]);
    }

    // ── JSON API: Webhook Management ──────────────────────────────────────────

    private function resolveInstance(int $id): WhatsappInstance
    {
        $user     = Auth::user();
        $instance = WhatsappInstance::whereNull('deleted_at')->findOrFail($id);

        if ($user->isSuperAdmin()) return $instance;
        if ($user->isClientAdmin() && $instance->client_id == $user->client_id) return $instance;
        if ($instance->owner_type == 'user' && $instance->owner_id == $user->id) return $instance;

        abort(403);
    }

    private function deliveryRate($query): float
    {
        $total     = (clone $query)->outbound()->where('sent_at', '!=', null)->count();
        $delivered = (clone $query)->outbound()->whereIn('status', ['delivered', 'read'])->count();
        return $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
    }
}