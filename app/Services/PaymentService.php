<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CreditOrder;
use App\Models\CreditPackage;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private readonly CreditService $creditService)
    {
    }

    // ── Razorpay ──────────────────────────────────────────────────────────────

    /**
     * Create a Razorpay order.
     * Returns order details to pass to Razorpay checkout JS.
     */
    public function createRazorpayOrder(CreditPackage $package, User $user): array
    {
        $client = $user->client;

        $order = CreditOrder::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_number' => CreditOrder::generateOrderNumber(),
            'credits' => $package->credits,
            'amount' => $package->price,
            'currency' => $package->currency,
            'gateway' => 'razorpay',
            'status' => CreditOrder::STATUS_PENDING,
        ]);

        // Create order in Razorpay
        $response = Http::withBasicAuth(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret'),
        )->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) ($package->price * 100), // paise
                    'currency' => $package->currency,
                    'receipt' => $order->order_number,
                    'notes' => [
                        'client_id' => $client->id,
                        'package_id' => $package->id,
                        'credits' => $package->credits,
                    ],
                ]);

        if (!$response->successful()) {
            $order->update(['status' => CreditOrder::STATUS_FAILED, 'failure_reason' => $response->body()]);
            throw new \RuntimeException('Failed to create Razorpay order: ' . $response->body());
        }

        $rzpOrder = $response->json();
        $order->update(['gateway_order_id' => $rzpOrder['id']]);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'razorpay_order_id' => $rzpOrder['id'],
            'amount' => $rzpOrder['amount'],
            'currency' => $rzpOrder['currency'],
            'key_id' => config('services.razorpay.key_id'),
            'name' => config('app.name'),
            'description' => "{$package->credits} instance credits — {$package->name}",
            'prefill' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    /**
     * Verify Razorpay payment signature and credit the wallet.
     */
    public function verifyRazorpayPayment(
        int $orderId,
        string $razorpayPaymentId,
        string $razorpayOrderId,
        string $razorpaySignature,
    ): CreditOrder {
        $order = CreditOrder::where('id', $orderId)
            ->where('gateway', 'razorpay')
            ->where('status', CreditOrder::STATUS_PENDING)
            ->firstOrFail();

        // Verify HMAC signature
        $expectedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            config('services.razorpay.key_secret'),
        );

        if (!hash_equals($expectedSignature, $razorpaySignature)) {
            $order->update(['status' => CreditOrder::STATUS_FAILED, 'failure_reason' => 'Signature mismatch.']);
            throw new \RuntimeException('Payment verification failed: invalid signature.');
        }

        return $this->fulfillOrder($order, $razorpayPaymentId, $razorpaySignature);
    }

    // ── Stripe ────────────────────────────────────────────────────────────────

    /**
     * Create a Stripe PaymentIntent.
     */
    public function createStripeIntent(CreditPackage $package, User $user): array
    {
        $client = $user->client;

        $order = CreditOrder::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_number' => CreditOrder::generateOrderNumber(),
            'credits' => $package->credits,
            'amount' => $package->price,
            'currency' => strtolower($package->currency),
            'gateway' => 'stripe',
            'status' => CreditOrder::STATUS_PENDING,
        ]);

        $response = Http::withToken(config('services.stripe.secret_key'))
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => (int) ($package->price * 100),
                'currency' => strtolower($package->currency),
                'description' => "WAP: {$package->name} — {$package->credits} credits",
                'metadata' => [
                    'order_id' => $order->id,
                    'client_id' => $client->id,
                    'package_id' => $package->id,
                ],
            ]);

        if (!$response->successful()) {
            $order->update(['status' => CreditOrder::STATUS_FAILED, 'failure_reason' => $response->body()]);
            throw new \RuntimeException('Failed to create Stripe PaymentIntent.');
        }

        $intent = $response->json();
        $order->update(['gateway_order_id' => $intent['id']]);

        return [
            'order_id' => $order->id,
            'client_secret' => $intent['client_secret'],
            'publishable_key' => config('services.stripe.publishable_key'),
            'amount' => $intent['amount'],
            'currency' => $intent['currency'],
        ];
    }

    /**
     * Handle Stripe webhook — confirm payment and credit wallet.
     */
    public function handleStripeWebhook(string $payload, string $sigHeader): void
    {
        $secret = config('services.stripe.webhook_secret');

        // Verify Stripe signature
        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $sig = null;
        foreach ($parts as $part) {
            [$k, $v] = explode('=', $part, 2);
            if ($k === 't')
                $timestamp = $v;
            if ($k === 'v1')
                $sig = $v;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('Invalid Stripe webhook signature.');
        }

        $event = json_decode($payload, true);

        if ($event['type'] === 'payment_intent.succeeded') {
            $intent = $event['data']['object'];
            $orderId = $intent['metadata']['order_id'] ?? null;
            if (!$orderId)
                return;

            $order = CreditOrder::where('id', $orderId)
                ->where('gateway', 'stripe')
                ->where('status', CreditOrder::STATUS_PENDING)
                ->first();

            if ($order) {
                $this->fulfillOrder($order, $intent['id']);
            }
        }
    }

    // ── Fulfillment ───────────────────────────────────────────────────────────

    /**
     * Mark order as paid and credit the client's wallet.
     */
    private function fulfillOrder(CreditOrder $order, string $paymentId, string $signature = ''): CreditOrder
    {
        return DB::transaction(function () use ($order, $paymentId, $signature) {
            $order->update([
                'status' => CreditOrder::STATUS_PAID,
                'gateway_payment_id' => $paymentId,
                'gateway_signature' => $signature,
                'paid_at' => now(),
            ]);

            // Add credits to client wallet
            $this->creditService->addToClient(
                client: $order->client,
                credits: $order->credits,
                type: \App\Models\CreditTransaction::TYPE_PURCHASE,
                reference: "Order #{$order->order_number} via {$order->gateway}",
                packageId: $order->package_id,
            );

            AuditLog::record(
                event: 'credit.purchased',
                auditable: $order,
                newValues: ['credits' => $order->credits, 'amount' => $order->amount, 'gateway' => $order->gateway],
                userId: $order->user_id,
            );

            Log::info("Credit order fulfilled: #{$order->order_number} — {$order->credits} credits → client {$order->client_id}");

            return $order->fresh();
        });
    }
}