<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Web\CreditController
 *
 * Handles credit ledger reads from the dashboard.
 * Session auth — registered in web.php.
 */
class CreditController extends Controller
{
    /**
     * GET /dashboard/credits/ledger
     * End user's own credit transaction history.
     */
    public function userLedger(Request $request): JsonResponse
    {
        $user = Auth::user();

        $ledger = CreditTransaction::where('owner_id', $user->id)
            ->where('owner_type', 'user')
            ->with(['instance:id,name,phone_number', 'package:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->through(fn($tx) => $this->formatTx($tx));

        return response()->json(['success' => true, 'data' => $ledger]);
    }

    /**
     * GET /client/credits/ledger
     * Client admin's client-level credit wallet history.
     */
    public function clientLedger(Request $request): JsonResponse
    {
        $client = Auth::user()->client;

        $ledger = CreditTransaction::where('owner_id', $client->id)
            ->where('owner_type', 'client')
            ->with(['instance:id,name,phone_number', 'package:id,name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->through(fn($tx) => $this->formatTx($tx));

        return response()->json(['success' => true, 'data' => $ledger]);
    }

    private function formatTx(CreditTransaction $tx): array
    {
        return [
            'id'            => $tx->id,
            'type'          => $tx->type,
            'credits'       => $tx->credits,
            'balance_after' => $tx->balance_after,
            'reference'     => $tx->reference,
            'instance'      => $tx->instance?->only('id', 'name', 'phone_number'),
            'package'       => $tx->package?->only('id', 'name'),
            'created_by'    => $tx->createdBy?->only('id', 'name'),
            'created_at'    => $tx->created_at->toIso8601String(),
        ];
    }
}