<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\BaileysClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GatewayController
 *
 * The EXTERNAL WhatsApp messaging API.
 * Protected by api.token middleware (Bearer token + X-Instance-Token).
 * This is what developers call from their own apps, scripts, or Postman.
 *
 * All send endpoints require:
 *   Authorization: Bearer wap_<user_api_token>
 *   X-Instance-Token: <instance_token>
 */
class GatewayController extends Controller
{
    public function __construct(private readonly BaileysClient $baileys) {}

    /**
     * GET /api/gateway/me
     * Verify the bearer token and return identity.
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->role,
                'credit_balance' => $user->credit_balance,
                'client_id'      => $user->client_id,
            ],
        ]);
    }

    /**
     * GET /api/gateway/instances
     * List caller's instances (read-only, no X-Instance-Token needed).
     */
    public function listInstances(): JsonResponse
    {
        $user = Auth::user();

        $instances = WhatsappInstance::whereNull('deleted_at')
            ->when($user->isSuperAdmin(), fn($q) => $q)
            ->when($user->isClientAdmin(), fn($q) => $q->where('client_id', $user->client_id))
            ->when($user->isUser(), fn($q) => $q->where('owner_id', $user->id)->where('owner_type', 'user'))
            ->get(['id', 'name', 'phone_number', 'instance_token', 'status', 'expires_at'])
            ->map(fn($i) => [
                'id'             => $i->id,
                'name'           => $i->name,
                'phone_number'   => $i->phone_number,
                'instance_token' => $i->instance_token,
                'status'         => $i->status,
                'expires_at'     => $i->expires_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $instances]);
    }

    /**
     * GET /api/gateway/instances/{id}/status
     * Live status of a specific instance.
     */
    public function instanceStatus(int $id): JsonResponse
    {
        $instance = $this->resolveInstance($id);
        $live     = $this->baileys->getStatus($instance->instance_token);

        return response()->json([
            'success'      => true,
            'data'         => [
                'id'           => $instance->id,
                'name'         => $instance->name,
                'phone_number' => $instance->phone_number,
                'db_status'    => $instance->status,
                'live_status'  => $live['status'] ?? 'unknown',
            ],
        ]);
    }

    /**
     * POST /api/gateway/send/text
     * Send a plain text message.
     *
     * Headers required:
     *   Authorization: Bearer <token>
     *   X-Instance-Token: <instance_token>
     *
     * Body:
     *   { "to": "919876543210", "message": "Hello!" }
     */
    public function sendText(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => ['required', 'string'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        return $this->sendViaInstance($request, [
            'type'    => 'text',
            'to'      => $request->to,
            'message' => $request->message,
        ]);
    }

    public function sendImage(Request $request): JsonResponse
    {
        $request->validate([
            'to'        => ['required', 'string'],
            'media_url' => ['required', 'url'],
            'caption'   => ['sometimes', 'string', 'max:1024'],
        ]);

        return $this->sendViaInstance($request, [
            'type'      => 'image',
            'to'        => $request->to,
            'media_url' => $request->media_url,
            'caption'   => $request->caption,
        ]);
    }

    public function sendVideo(Request $request): JsonResponse
    {
        $request->validate([
            'to'        => ['required', 'string'],
            'media_url' => ['required', 'url'],
            'caption'   => ['sometimes', 'string', 'max:1024'],
        ]);

        return $this->sendViaInstance($request, [
            'type'      => 'video',
            'to'        => $request->to,
            'media_url' => $request->media_url,
            'caption'   => $request->caption,
        ]);
    }

    public function sendAudio(Request $request): JsonResponse
    {
        $request->validate([
            'to'         => ['required', 'string'],
            'media_url'  => ['required', 'url'],
            'voice_note' => ['sometimes', 'boolean'],
        ]);

        return $this->sendViaInstance($request, [
            'type'       => 'audio',
            'to'         => $request->to,
            'media_url'  => $request->media_url,
            'voice_note' => $request->boolean('voice_note', false),
        ]);
    }

    public function sendDocument(Request $request): JsonResponse
    {
        $request->validate([
            'to'        => ['required', 'string'],
            'media_url' => ['required', 'url'],
            'filename'  => ['sometimes', 'string'],
            'mimetype'  => ['sometimes', 'string'],
        ]);

        return $this->sendViaInstance($request, [
            'type'      => 'document',
            'to'        => $request->to,
            'media_url' => $request->media_url,
            'filename'  => $request->filename,
            'mimetype'  => $request->mimetype,
        ]);
    }

    public function sendLocation(Request $request): JsonResponse
    {
        $request->validate([
            'to'        => ['required', 'string'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'name'      => ['sometimes', 'string'],
            'address'   => ['sometimes', 'string'],
        ]);

        return $this->sendViaInstance($request, [
            'type'      => 'location',
            'to'        => $request->to,
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
            'name'      => $request->name,
            'address'   => $request->address,
        ]);
    }

    public function sendPoll(Request $request): JsonResponse
    {
        $request->validate([
            'to'               => ['required', 'string'],
            'question'         => ['required', 'string', 'max:255'],
            'options'          => ['required', 'array', 'min:2', 'max:12'],
            'options.*'        => ['required', 'string'],
            'selectable_count' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->sendViaInstance($request, [
            'type'             => 'poll',
            'to'               => $request->to,
            'question'         => $request->question,
            'options'          => $request->options,
            'selectable_count' => $request->integer('selectable_count', 1),
        ]);
    }

    public function sendBulk(Request $request): JsonResponse
    {
        $request->validate([
            'recipients'   => ['required', 'array', 'min:1', 'max:1000'],
            'recipients.*' => ['required', 'string'],
            'type'         => ['required', 'string'],
            'message'      => ['required_if:type,text', 'string'],
            'media_url'    => ['required_unless:type,text,location,poll', 'url'],
        ]);

        // Phase 4 will implement proper campaign queue
        // For now, limit bulk to 50 and send synchronously
        $instance   = $request->_instance;
        $recipients = array_slice($request->recipients, 0, 50);
        $results    = [];

        foreach ($recipients as $to) {
            $payload = array_merge($request->except('recipients'), ['to' => $to]);
            $result  = $this->baileys->send($instance->instance_token, $payload);
            $results[] = ['to' => $to, 'success' => $result['success'], 'wa_message_id' => $result['wa_message_id'] ?? null];
        }

        return response()->json([
            'success' => true,
            'data'    => ['results' => $results, 'total' => count($results)],
        ]);
    }

    /**
     * GET /api/gateway/messages
     * Message log for the authenticated user's instances. Phase 3 will expand this.
     */
    public function messages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Full message log available in Phase 3.',
            'data'    => [],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Send through the instance already resolved by ApiTokenAuthentication middleware.
     * The middleware set $request->_instance from the X-Instance-Token header.
     */
    private function sendViaInstance(Request $request, array $payload): JsonResponse
    {
        $instance = $request->_instance;

        if (!$instance) {
            return response()->json([
                'success' => false,
                'message' => 'X-Instance-Token header is required for send endpoints.',
            ], 422);
        }

        if (!$instance->isSendable()) {
            return response()->json([
                'success' => false,
                'message' => "Instance is not ready to send. Status: {$instance->status}.",
            ], 422);
        }

        $result = $this->baileys->send($instance->instance_token, $payload);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to send message.',
            ], 500);
        }

        return response()->json([
            'success'       => true,
            'wa_message_id' => $result['wa_message_id'],
            'timestamp'     => $result['timestamp'] ?? null,
        ]);
    }

    private function resolveInstance(int $id): WhatsappInstance
    {
        $user     = Auth::user();
        $instance = WhatsappInstance::whereNull('deleted_at')->findOrFail($id);

        if ($user->isSuperAdmin())                                                  return $instance;
        if ($user->isClientAdmin() && $instance->client_id === $user->client_id)   return $instance;
        if ($instance->owner_type === 'user' && $instance->owner_id === $user->id) return $instance;

        abort(403);
    }
}