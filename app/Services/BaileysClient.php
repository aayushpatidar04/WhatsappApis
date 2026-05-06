<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaileysClient
{
    private string $baseUrl;
    private string $secret;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.baileys.url', 'http://127.0.0.1:3000'), '/');
        $this->secret  = config('services.baileys.secret', '');
        $this->timeout = 10;
    }

    // ─── Health ───────────────────────────────────────────────────────────────

    /**
     * Check if the Baileys Node.js service is reachable and healthy.
     */
    public function health(): array
    {
        try {
            $response = $this->get('/health', timeout: 5);

            return [
                'online'   => $response->successful(),
                'status'   => $response->json('status', 'unknown'),
                'sessions' => $response->json('sessions', 0),
                'uptime'   => $response->json('uptime', 0),
            ];
            // return [
            //     'online'   => false,
            //     'status'   => 'unreachable',
            //     'sessions' => 0,
            //     'uptime'   => 0,
            // ];
        } catch (ConnectionException $e) {
            Log::warning('Baileys service unreachable', ['error' => $e->getMessage()]);

            return [
                'online'   => false,
                'status'   => 'unreachable',
                'sessions' => 0,
                'uptime'   => 0,
                'error'    => $e->getMessage(),
            ];
        }
    }

    // ─── Session management (Phase 2 — stubs in Phase 1) ─────────────────────

    /**
     * Initialise a new Baileys session for the given instance_token.
     * Phase 1: returns 501 stub. Phase 2: real implementation.
     */
    public function createSession(string $instanceToken): array
    {
        return $this->post("/instance/{$instanceToken}/create");
    }

    /**
     * Get the current QR code (base64 PNG) for an unconnected session.
     */
    public function getQrCode(string $instanceToken): array
    {
        return $this->get("/instance/{$instanceToken}/qr")->json();
    }

    /**
     * Get the live connection status of an instance.
     */
    public function getStatus(string $instanceToken): array
    {
        return $this->get("/instance/{$instanceToken}/status")->json();
    }

    /**
     * Gracefully logout and destroy a Baileys session.
     */
    public function logout(string $instanceToken): array
    {
        return $this->post("/instance/{$instanceToken}/logout");
    }

    /**
     * Delete all session data for an instance (called after grace period).
     */
    public function deleteSession(string $instanceToken): array
    {
        return $this->delete("/instance/{$instanceToken}");
    }

    // ─── Messaging (Phase 3 — stubs in Phase 1) ──────────────────────────────

    public function sendMessage(string $instanceToken, array $payload): array
    {
        return $this->post("/instance/{$instanceToken}/send", $payload);
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────────

    private function http(int $timeout = 0): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($timeout ?: $this->timeout)
            ->withHeaders([
                'X-Internal-Secret' => $this->secret,
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
            ])
            ->throw(); // Handle errors manually
    }

    private function get(string $path, int $timeout = 0): Response
    {
        return $this->http($timeout)->get($path);
    }

    private function post(string $path, array $data = [], int $timeout = 0): array
    {
        try {
            $response = $this->http($timeout)->post($path, $data);

            if ($response->status() === 501) {
                return ['success' => false, 'message' => 'Not implemented yet (Phase 1)'];
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            Log::error('BaileysClient POST failed', ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Service unreachable'];
        }
    }

    private function delete(string $path): array
    {
        try {
            return $this->http()->delete($path)->json() ?? [];
        } catch (ConnectionException $e) {
            Log::error('BaileysClient DELETE failed', ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Service unreachable'];
        }
    }
}