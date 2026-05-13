<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly CreditService $creditService)
    {
    }

    /**
     * GET /client/users
     * List all users under this client's tenant.
     */
    public function index(Request $request): Response
    {
        $users = User::withTrashed()->where('client_id', Auth::user()->client_id)
            ->where('role', 'user')
            ->withCount('instances')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Client/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * POST /client/users
     * Create a new end user under this tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'credits' => ['sometimes', 'integer', 'min:0'],  // Initial credits to give user
        ]);

        $user = User::create([
            'client_id' => $admin->client_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);

        // Optionally transfer initial credits from client wallet to user
        if (!empty($validated['credits']) && $validated['credits'] > 0) {
            $this->creditService->addToUser(
                user: $user,
                credits: $validated['credits'],
                type: 'allocation',
                reference: "Initial credit allocation by admin {$admin->email}",
                actorId: $admin->id,
            );
        }

        return response()->json([
            'success' => true,
            'message' => "User '{$user->email}' created successfully.",
            'data' => $user->only('id', 'name', 'email', 'credit_balance', 'created_at'),
        ], 201);
    }

    /**
     * PATCH /client/users/{id}
     * Update user details or active status.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->findInTenant($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($user, $validated) {
            if (array_key_exists('is_active', $validated)) {
                if ($validated['is_active']) {
                    $user->restore();
                    $user->is_active = true;
                } else {
                    $user->is_active = false;
                    $user->delete();
                }
            }
            $user->update($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'data' => $user->fresh()->only('id', 'name', 'email', 'is_active', 'credit_balance'),
        ]);
    }

    /**
     * POST /client/users/{id}/credits
     * Allocate credits from client wallet to a user.
     */
    public function allocateCredits(Request $request, int $id): JsonResponse
    {
        $admin = Auth::user();
        $user = $this->findInTenant($id);
        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:1'],
            'reference' => ['sometimes', 'string', 'max:200'],
        ]);

        // Deduct from client wallet
        $client = $admin->client;
        if ($client->credit_balance < $validated['credits']) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient client credits. Available: {$client->credit_balance}.",
            ], 422);
        }

        // Deduct from client
        $this->creditService->addToClient(
            client: $client,
            credits: -$validated['credits'],
            type: 'allocation',
            reference: $validated['reference'] ?? "Allocated to user {$user->email}",
            actorId: $admin->id,
        );

        // Add to user
        $this->creditService->addToUser(
            user: $user,
            credits: $validated['credits'],
            type: 'allocation',
            reference: $validated['reference'] ?? "From client admin {$admin->email}",
            actorId: $admin->id,
        );

        return response()->json([
            'success' => true,
            'message' => "{$validated['credits']} credits allocated to {$user->name}.",
            'data' => [
                'user_balance' => $user->fresh()->credit_balance,
                'client_balance' => $client->fresh()->credit_balance,
            ],
        ]);
    }

    private function findInTenant(int $id): User
    {
        return User::withTrashed()->where('id', $id)
            ->where('client_id', Auth::user()->client_id)
            ->where('role', 'user')
            ->firstOrFail();
    }
}