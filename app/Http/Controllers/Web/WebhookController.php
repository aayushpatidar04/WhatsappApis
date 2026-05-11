<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function page(): Response
    {
        return Inertia::render('User/Webhooks');
    }

    public function index(): JsonResponse
    {
        $webhooks = Webhook::forUser(Auth::id())
            ->with('instance:id,name,phone_number')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($w) => $this->format($w));

        return response()->json(['success' => true, 'data' => $webhooks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'url'         => ['required', 'url', 'max:500', 'starts_with:https://'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['in:message.inbound,message.ack,message.sent,message.failed,instance.connected,instance.disconnected,instance.expiring'],
            'instance_id' => ['sometimes', 'nullable', 'integer', 'exists:whatsapp_instances,id'],
        ]);

        $user    = Auth::user();
        $webhook = Webhook::create([
            'user_id'     => $user->id,
            'client_id'   => $user->client_id,
            'instance_id' => $validated['instance_id'] ?? null,
            'name'        => $validated['name'],
            'url'         => $validated['url'],
            'secret'      => Webhook::generateSecret(),
            'events'      => $validated['events'],
            'is_active'   => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook created.',
            'data'    => $this->format($webhook),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook   = Webhook::forUser(Auth::id())->findOrFail($id);
        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:100'],
            'url'       => ['sometimes', 'url', 'max:500', 'starts_with:https://'],
            'events'    => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($validated);

        return response()->json(['success' => true, 'data' => $this->format($webhook->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        Webhook::forUser(Auth::id())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Webhook deleted.']);
    }

    public function ping(int $id): JsonResponse
    {
        $webhook = Webhook::forUser(Auth::id())->findOrFail($id);
        $result  = $this->webhookService->ping($webhook);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function logs(int $id): JsonResponse
    {
        $webhook = Webhook::forUser(Auth::id())->findOrFail($id);

        $logs = WebhookLog::where('webhook_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'event', 'http_status', 'success', 'attempt', 'duration_ms', 'response_body', 'created_at'])
            ->map(fn($l) => [
                'id'           => $l->id,
                'event'        => $l->event,
                'http_status'  => $l->http_status,
                'success'      => $l->success,
                'attempt'      => $l->attempt,
                'duration_ms'  => $l->duration_ms,
                'response_body'=> $l->response_body,
                'created_at'   => $l->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    private function format(Webhook $w): array
    {
        return [
            'id'                 => $w->id,
            'name'               => $w->name,
            'url'                => $w->url,
            'secret'             => $w->secret,
            'events'             => $w->events,
            'is_active'          => $w->is_active,
            'failure_count'      => $w->failure_count,
            'last_triggered_at'  => $w->last_triggered_at?->toIso8601String(),
            'instance'           => $w->instance?->only('id', 'name', 'phone_number'),
            'created_at'         => $w->created_at->toIso8601String(),
        ];
    }
}