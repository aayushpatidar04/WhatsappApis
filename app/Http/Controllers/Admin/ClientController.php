<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * GET /super/clients
     * Inertia page — list all clients.
     */
    public function index(): Response
    {
        $clients = Client::withTrashed()->withCount(['users', 'allInstances'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
        ]);
    }

    /**
     * POST /super/clients
     * Create a new client tenant + their master admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'max_rate_per_minute' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'max_instances_per_user' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            // Create the client tenant
            $client = Client::create([
                'name' => $validated['client_name'],
                'super_admin_id' => Auth::id(),
                'max_rate_per_minute' => $validated['max_rate_per_minute'] ?? 20,
                'max_instances_per_user' => $validated['max_instances_per_user'] ?? 5,
            ]);

            // Create the master admin user
            $admin = User::create([
                'client_id' => $client->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'role' => 'client_admin',
                'is_active' => true,
            ]);

            // Link super_admin_id back now that we have the admin user
            // (super_admin_id on clients is the platform SA, not the client admin)

            return ['client' => $client, 'admin' => $admin];
        });

        return response()->json([
            'success' => true,
            'message' => "Client '{$result['client']->name}' created with Master Admin '{$result['admin']->email}'.",
            'data' => [
                'client' => $result['client'],
                'admin' => $result['admin']->only('id', 'name', 'email', 'role'),
            ],
        ], 201);
    }

    /**
     * PATCH /super/clients/{id}
     * Update client settings.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::withTrashed()->findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'max_rate_per_minute' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'max_instances_per_user' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('is_active', $validated)) {
            if ($validated['is_active']) {
                $client->restore();
                $client->update(['is_active' => true]);
                $client->users()->update(['is_active' => true]);
            }
        }

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Client updated.',
            'data' => $client->fresh(),
        ]);
    }

    /**
     * DELETE /super/clients/{id}
     * Soft-delete a client and all their users.
     */
    public function destroy(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        DB::transaction(function () use ($client) {
            $client->users()->update(['is_active' => false]);
            $client->update(['is_active' => false]);
            $client->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Client '{$client->name}' has been suspended.",
        ]);
    }
}