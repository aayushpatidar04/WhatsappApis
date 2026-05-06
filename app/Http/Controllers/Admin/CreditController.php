<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $creditService) {}

    /**
     * POST /admin/credits/adjust
     * Super Admin manually adjusts a user's or client's credit balance.
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_type' => ['required', 'in:user,client'],
            'owner_id'   => ['required', 'integer'],
            'credits'    => ['required', 'integer', 'not_in:0'],  // positive or negative
            'reference'  => ['required', 'string', 'max:200'],
        ]);

        $actor = auth()->user();

        if ($validated['owner_type'] === 'client') {
            $owner = Client::findOrFail($validated['owner_id']);
            $tx    = $this->creditService->addToClient(
                client:    $owner,
                credits:   $validated['credits'],
                reference: $validated['reference'],
                actorId:   $actor->id,
            );
        } else {
            $owner = User::findOrFail($validated['owner_id']);
            $tx    = $this->creditService->addToUser(
                user:      $owner,
                credits:   $validated['credits'],
                reference: $validated['reference'],
                actorId:   $actor->id,
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Credits adjusted. New balance: {$tx->balance_after}.",
            'data'    => $tx,
        ]);
    }

    /**
     * GET /admin/credits/ledger
     * Super Admin views the full credit transaction ledger.
     */
    public function ledger(Request $request): JsonResponse
    {
        $query = CreditTransaction::with(['instance:id,name', 'package:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(50),
        ]);
    }

    // ─── Credit Packages ──────────────────────────────────────────────────────

    public function packagesIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => CreditPackage::orderBy('credits')->get(),
        ]);
    }

    public function packagesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'credits'       => ['required', 'integer', 'min:1'],
            'price'         => ['required', 'numeric', 'min:0'],
            'currency'      => ['required', 'string', 'size:3'],
            'validity_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'description'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $package = CreditPackage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Credit package created.',
            'data'    => $package,
        ], 201);
    }

    public function packagesUpdate(Request $request, int $id): JsonResponse
    {
        $package   = CreditPackage::findOrFail($id);
        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:100'],
            'credits'       => ['sometimes', 'integer', 'min:1'],
            'price'         => ['sometimes', 'numeric', 'min:0'],
            'is_active'     => ['sometimes', 'boolean'],
            'description'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $package->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $package->fresh(),
        ]);
    }
}