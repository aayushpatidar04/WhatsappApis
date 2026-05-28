<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditOrder;
use App\Models\CreditPackage;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingController
 *
 * Client admins use this to purchase credit packages.
 * Supports Razorpay (primary) and Stripe (secondary).
 * Session auth — no Bearer token.
 */
class BillingController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * GET /client/billing
     * Credit packages + order history page.
     */
    public function page(): Response
    {
        $user   = Auth::user();
        $client = $user->client;

        $packages = CreditPackage::active()->orderBy('credits')->get();

        $orders = CreditOrder::forClient($client->id)
            ->with('package:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'package'      => $o->package?->only('id', 'name'),
                'credits'      => $o->credits,
                'amount'       => $o->amount,
                'currency'     => $o->currency,
                'gateway'      => $o->gateway,
                'status'       => $o->status,
                'paid_at'      => $o->paid_at?->toIso8601String(),
                'created_at'   => $o->created_at->toIso8601String(),
            ]);

        return Inertia::render('Client/Billing', [
            'packages'       => $packages,
            'orders'         => $orders,
            'credit_balance' => $client->credit_balance,
            'gateway'        => config('services.payment.default', 'razorpay'),
        ]);
    }

    /**
     * POST /client/billing/initiate
     * Create a payment gateway order and return checkout details.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => ['required', 'integer', 'exists:credit_packages,id'],
            'gateway'    => ['sometimes', 'in:razorpay,stripe'],
        ]);

        $package = CreditPackage::active()->findOrFail($request->integer('package_id'));
        $user    = Auth::user();
        $gateway = $request->input('gateway', config('services.payment.default', 'razorpay'));
        
        try {
            $data = match ($gateway) {
                'stripe'   => $this->paymentService->createStripeIntent($package, $user),
                default    => $this->paymentService->createRazorpayOrder($package, $user),
            };

            return response()->json(['success' => true, 'gateway' => $gateway, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /client/billing/verify/razorpay
     * Verify Razorpay payment after checkout completes.
     */
    public function verifyRazorpay(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'            => ['required', 'integer'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        // Ensure the order belongs to this client
        $dbOrder = CreditOrder::where('id', $request->order_id)
            ->where('client_id', Auth::user()->client_id)
            ->firstOrFail();

        try {
            $order = $this->paymentService->verifyRazorpayPayment(
                orderId:            $dbOrder->id,
                razorpayPaymentId:  $request->razorpay_payment_id,
                razorpayOrderId:    $request->razorpay_order_id,
                razorpaySignature:  $request->razorpay_signature,
            );

            return response()->json([
                'success'       => true,
                'message'       => "{$order->credits} credits added to your wallet!",
                'order_number'  => $order->order_number,
                'credits'       => $order->credits,
                'new_balance'   => $order->client->fresh()->credit_balance,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/stripe/webhook
     * Stripe sends payment events here. Not session-auth — verified by signature.
     * Note: This route must be in api.php and excluded from CSRF.
     */
    public function stripeWebhook(Request $request): \Illuminate\Http\Response
    {
        try {
            $this->paymentService->handleStripeWebhook(
                payload:   $request->getContent(),
                sigHeader: $request->header('Stripe-Signature', ''),
            );
            return response('OK', 200);
        } catch (\Throwable $e) {
            return response($e->getMessage(), 400);
        }
    }

    /**
     * GET /client/billing/orders
     * JSON order history for async load.
     */
    public function orders(Request $request): JsonResponse
    {
        $orders = CreditOrder::forClient(Auth::user()->client_id)
            ->with('package:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15))
            ->through(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'package'      => $o->package?->only('id', 'name'),
                'credits'      => $o->credits,
                'amount'       => (float) $o->amount,
                'currency'     => $o->currency,
                'gateway'      => $o->gateway,
                'status'       => $o->status,
                'paid_at'      => $o->paid_at?->toIso8601String(),
                'created_at'   => $o->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $orders]);
    }
}