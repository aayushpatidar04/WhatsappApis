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
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $creditService)
    {
    }

    /**
     * POST /admin/credits/adjust
     * Super Admin manually adjusts a user's or client's credit balance.
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_type' => ['required', 'in:user,client'],
            'owner_id' => ['required', 'integer'],
            'credits' => ['required', 'integer', 'not_in:0'],  // positive or negative
            'reference' => ['required', 'string', 'max:200'],
        ]);

        $actor = Auth::user();

        if ($validated['owner_type'] == 'client') {
            $owner = Client::findOrFail($validated['owner_id']);
            $tx = $this->creditService->addToClient(
                client: $owner,
                credits: $validated['credits'],
                reference: $validated['reference'],
                actorId: $actor->id,
            );
        } else {
            $owner = User::findOrFail($validated['owner_id']);
            $tx = $this->creditService->addToUser(
                user: $owner,
                credits: $validated['credits'],
                reference: $validated['reference'],
                actorId: $actor->id,
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Credits adjusted. New balance: {$tx->balance_after}.",
            'data' => $tx,
        ]);
    }

    /**
     * GET /admin/credits/ledger
     * Super Admin views the full credit transaction ledger.
     */
    public function ledger(Request $request): Response
    {
        $query = CreditTransaction::with([
            'instance:id,name',
            'package:id,name',
            'client:id,name',
            'createdBy:id,name,email'
        ])
            ->orderByDesc('created_at');

        // Filter by client name instead of ID
        if ($request->filled('client_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->client_name . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/CreditLedgers', [
            'transactions' => $transactions,
            'filters' => $request->only(['client_name', 'type']),
        ]);
    }

}