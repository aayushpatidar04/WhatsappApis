<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactGroup;
use App\Models\WhatsappInstance;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService)
    {
    }

    public function page(): Response
    {
        return Inertia::render('User/Campaigns');
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Campaign::with(['instance:id,name,phone_number', 'contactGroup:id,name'])
            ->when($user->isClientAdmin(), fn($q) => $q->forClient($user->client_id))
            ->when($user->isUser(), fn($q) => $q->forUser($user->id))
            ->orderByDesc('created_at');

        if ($request->filled('status'))
            $query->where('status', $request->status);

        $campaigns = $query->paginate($request->integer('per_page', 15))
            ->through(fn($c) => $this->format($c));

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instance_id' => ['required', 'integer'],
            'message_type' => ['required', 'in:text,image,video,audio,document,location,poll,template'],
            'message_payload' => ['required', 'array'],
            'schedule_time' => ['sometimes', 'nullable', 'date', 'after:now'],
            'send_window_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'send_window_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'contact_group_id' => ['sometimes', 'nullable', 'integer'],
            // Recipients
            'contact_ids' => ['sometimes', 'array'],
            'phones' => ['sometimes', 'array'],
        ]);

        $this->authoriseInstance($validated['instance_id']);

        // 👇 Add status based on schedule_time
        if (!empty($validated['schedule_time'])) {
            $validated['status'] = 'scheduled';
        } else {
            $validated['status'] = 'draft';
        }

        $campaign = $this->campaignService->create(Auth::user(), $validated);

        // Add recipients if provided
        if (!empty($validated['contact_group_id']) || !empty($validated['contact_ids']) || !empty($validated['phones'])) {
            $this->campaignService->addRecipients($campaign, $validated);
        }

        return response()->json(['success' => true, 'data' => $this->format($campaign->load('instance', 'contactGroup'))], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $campaign = $this->resolve($id)->load(['instance:id,name,phone_number', 'contactGroup:id,name']);
        return response()->json(['success' => true, 'data' => $this->format($campaign, detailed: true)]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = $this->resolve($id);

        if (!$campaign->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be edited in its current state.'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'message_payload' => ['sometimes', 'array'],
            'schedule_time' => ['sometimes', 'nullable', 'date'],
            'send_window_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'send_window_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            
            // 👇 Added recipient validation rules
            'contact_group_id' => ['sometimes', 'nullable', 'integer'],
            'contact_ids' => ['sometimes', 'array'],
            'phones' => ['sometimes', 'array'],
        ]);

        // 1. Update core campaign fields
        $campaign->update($validated);

        // 2. Handle recipient updates if they were included in the request
        if ($request->hasAny(['contact_group_id', 'contact_ids', 'phones'])) {
            
            // 👇 Call a method on your service to handle the syncing
            $this->campaignService->syncRecipients($campaign, $validated);
        }

        return response()->json([
            'success' => true, 
            'data' => $this->format($campaign->fresh(['instance', 'contactGroup'])) // Load relations just like store()
        ]);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function launch(int $id): JsonResponse
    {
        $campaign = $this->resolve($id);
        try {
            $this->campaignService->launch($campaign);
            return response()->json(['success' => true, 'message' => 'Campaign launched.', 'status' => Campaign::STATUS_RUNNING]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pause(int $id): JsonResponse
    {
        $campaign = $this->resolve($id);
        $this->campaignService->pause($campaign);
        return response()->json(['success' => true, 'message' => 'Campaign paused.', 'status' => Campaign::STATUS_PAUSED]);
    }

    public function resume(int $id): JsonResponse
    {
        $campaign = $this->resolve($id);
        try {
            $this->campaignService->resume($campaign);
            return response()->json(['success' => true, 'message' => 'Campaign resumed.', 'status' => Campaign::STATUS_RUNNING]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $campaign = $this->resolve($id);
        $this->campaignService->cancel($campaign);
        return response()->json(['success' => true, 'message' => 'Campaign cancelled.', 'status' => Campaign::STATUS_CANCELLED]);
    }

    // ── Recipients ────────────────────────────────────────────────────────────

    public function recipients(Request $request, int $id): JsonResponse
    {
        $campaign = $this->resolve($id);
        $recipients = $campaign->recipients()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->through(fn($r) => [
                'id' => $r->id,
                'phone' => $r->phone,
                'name' => $r->name,
                'status' => $r->status,
                'error_message' => $r->error_message,
                'sent_at' => $r->sent_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $recipients]);
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    public function analytics(int $id): JsonResponse
    {
        $campaign = $this->resolve($id);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $campaign->total_recipients,
                'sent' => $campaign->sent_count,
                'delivered' => $campaign->delivered_count,
                'read' => $campaign->read_count,
                'failed' => $campaign->failed_count,
                'pending' => max(0, $campaign->total_recipients - $campaign->sent_count - $campaign->failed_count),
                'progress_pct' => $campaign->progressPct(),
                'delivery_rate' => $campaign->deliveryRate(),
                'read_rate' => $campaign->sent_count > 0 ? round(($campaign->read_count / $campaign->sent_count) * 100, 1) : 0,
            ]
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolve(int $id): Campaign
    {
        $user = Auth::user();
        $campaign = Campaign::findOrFail($id);

        if ($user->isSuperAdmin())
            return $campaign;
        if ($user->isClientAdmin() && $campaign->client_id == $user->client_id)
            return $campaign;
        if ($campaign->user_id == $user->id)
            return $campaign;
        abort(403);
    }

    private function authoriseInstance(int $instanceId): void
    {
        $user = Auth::user();
        $instance = WhatsappInstance::findOrFail($instanceId);
        if (!$user->isSuperAdmin() && $instance->client_id !== $user->client_id)
            abort(403);
    }

    private function format(Campaign $c, bool $detailed = false): array
    {
        $data = [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'message_type' => $c->message_type,
            'message_payload' => $c->message_payload,
            'schedule_time' => $c->schedule_time?->toIso8601String(),
            'total_recipients' => $c->total_recipients,
            'sent_count' => $c->sent_count,
            'delivered_count' => $c->delivered_count,
            'read_count' => $c->read_count,
            'failed_count' => $c->failed_count,
            'progress_pct' => $c->progressPct(),
            'delivery_rate' => $c->deliveryRate(),
            'started_at' => $c->started_at?->toIso8601String(),
            'completed_at' => $c->completed_at?->toIso8601String(),
            'created_at' => $c->created_at->toIso8601String(),
            'instance' => $c->relationLoaded('instance') ? $c->instance?->only('id', 'name', 'phone_number') : null,
            'contact_group' => $c->relationLoaded('contactGroup') ? $c->contactGroup?->only('id', 'name') : null,
            'contact_group_id' => $c->contact_group_id,
            'recipients' => $c->recipients()->pluck('phone')->toArray(),
        ];

        if ($detailed) {
            $data['message_payload'] = $c->message_payload;
            $data['send_window_start'] = $c->send_window_start;
            $data['send_window_end'] = $c->send_window_end;
        }

        return $data;
    }
}