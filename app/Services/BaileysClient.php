<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BaileysClient — Laravel HTTP client wrapper for the Node.js microservice.
 * All methods communicate via internal REST API on localhost:3000.
 * Protected by X-Internal-Secret header.
 */
class BaileysClient
{
    private string $baseUrl;
    private string $secret;
    private int    $timeout;

    public function __construct()
    {
        // $this->baseUrl = rtrim(config('services.baileys.url', 'http://127.0.0.1:3000'), '/');
        $this->baseUrl = rtrim(config('services.baileys.url', 'https://wa-api.intouchsoftwaresolution.in'), '/');
        $this->secret  = config('services.baileys.secret', '');
        $this->timeout = (int) config('services.baileys.timeout', 10);
    }

    // ─── Health ───────────────────────────────────────────────────────────────

    public function health(): array
    {
        try {
            $res = $this->http()->get('/health');
            return array_merge(['online' => $res->successful()], $res->json() ?? []);
        } catch (ConnectionException) {
            return ['online' => false, 'sessions' => 0, 'error' => 'Service unreachable'];
        } catch (\Throwable $e) {
            return ['online' => false, 'sessions' => 0, 'error' => $e->getMessage()];
        }
    }

    // ─── Session lifecycle ────────────────────────────────────────────────────

    /**
     * Initialise a new Baileys session.
     * Returns immediately — QR is delivered via Pusher callback.
     */
    public function createSession(string $instanceToken): array
    {
        try {
            $res = $this->http()->post("/instance/{$instanceToken}/create");
            return $res->json() ?? ['success' => false];
        } catch (\Throwable $e) {
            Log::error("BaileysClient::createSession failed: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get current QR code (base64 PNG) for an instance awaiting scan.
     */
    public function getQrCode(string $instanceToken): array
    {
        try {
            $res = $this->http()->get("/instance/{$instanceToken}/qr");
            return $res->json() ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get live connection status from Baileys service.
     */
    public function getStatus(string $instanceToken): array
    {
        try {
            $res = $this->http()->get("/instance/{$instanceToken}/status");
            return $res->json() ?? ['status' => 'unknown'];
        } catch (\Throwable $e) {
            return ['status' => 'unreachable', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get WhatsApp account info for a connected instance.
     */
    public function getAccountInfo(string $instanceToken): array
    {
        try {
            $res = $this->http()->get("/instance/{$instanceToken}/account-info");
            return $res->json('data') ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Gracefully logout and clear session from Baileys.
     */
    public function logout(string $instanceToken): bool
    {
        try {
            $res = $this->http()->post("/instance/{$instanceToken}/logout");
            return $res->successful();
        } catch (\Throwable $e) {
            Log::error("BaileysClient::logout failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Hard-delete session data from Baileys (called after grace period).
     */
    public function deleteSession(string $instanceToken): bool
    {
        try {
            $res = $this->http()->delete("/instance/{$instanceToken}");
            return $res->successful();
        } catch (\Throwable $e) {
            Log::error("BaileysClient::deleteSession failed: {$e->getMessage()}");
            return false;
        }
    }

    // ─── Messaging ────────────────────────────────────────────────────────────

    /**
     * Send any message type through a connected instance.
     * Returns ['success', 'wa_message_id', 'timestamp'] on success.
     */
    public function send(string $instanceToken, array $payload): array
    {
        try {
            $res = $this->http()->timeout($this->timeout)->post(
                "/instance/{$instanceToken}/send",
                $payload
            );

            if ($res->successful()) {
                return $res->json() ?? ['success' => false];
            }

            Log::warning("BaileysClient::send non-2xx: {$res->status()}", $res->json() ?? []);
            return [
                'success' => false,
                'message' => $res->json('message') ?? "HTTP {$res->status()}",
            ];
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'Baileys service unreachable.'];
        } catch (\Throwable $e) {
            Log::error("BaileysClient::send error: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Groups ───────────────────────────────────────────────────────────────

    public function getGroups(string $instanceToken): array
    {
        try {
            $res = $this->http()->get("/instance/{$instanceToken}/groups");
            return $res->json('data') ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getGroupMeta(string $instanceToken, string $groupJid): array
    {
        try {
            $res = $this->http()->get("/instance/{$instanceToken}/groups/{$groupJid}");
            return $res->json('data') ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── HTTP client factory ─────────────────────────────────────────────────

    private function http()
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Internal-Secret' => $this->secret,
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
            ])
            ->timeout($this->timeout);
    }

    // Add this inside App\Services\BaileysClient

    /**
     * Get the status of all instances currently in Node.js memory.
     */
    public function getBulkStatus(): array
    {
        try {
            $res = $this->http()->get('/instances/bulk-status');
            return $res->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::error("BaileysClient::getBulkStatus failed: {$e->getMessage()}");
            return [];
        }
    }
}