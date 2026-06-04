<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client\SettingsController
 *
 * Client Admin manages workspace settings.
 * Different from Super Admin settings — these are client-level (tenant) settings.
 */
class SettingsController extends Controller
{
    /**
     * GET /client/settings
     */
    public function page(): Response
    {
        $client = Auth::user()->client;
        $user   = Auth::user();

        return Inertia::render('Client/Settings', [
            'client_name'         => $client->name,
            'client_contact_email'=> $client->contact_email,
            'user_name'           => $user->name,
            'user_email'          => $user->email,
            'max_rate_per_minute' => $client->max_rate_per_minute,
            'credit_balance'      => $client->credit_balance,
        ]);
    }

    /**
     * PATCH /client/settings/company
     * Update client company info (Master Admin only).
     */
    public function updateCompany(Request $request): JsonResponse
    {
        $client = Auth::user()->client;

        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:100'],
            'contact_email'   => ['sometimes', 'email'],
            'contact_phone'   => ['sometimes', 'phone', 'max:20'],
            'website'         => ['sometimes', 'nullable', 'url'],
            'timezone'        => ['sometimes', 'timezone'],
        ]);

        $old = $client->only(array_keys($validated));
        $client->update($validated);

        AuditLog::record('client.updated', $client, $old, $validated);

        return response()->json(['success' => true, 'message' => 'Company info updated.', 'data' => $client->fresh()]);
    }

    /**
     * PATCH /client/settings/profile
     * Update current user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
        ]);

        $old = $user->only(array_keys($validated));
        $user->update($validated);

        AuditLog::record('user.profile.updated', $user, $old, $validated);

        return response()->json(['success' => true, 'message' => 'Profile updated.']);
    }

    /**
     * POST /client/settings/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::record('user.password_changed', $user);

        return response()->json(['success' => true, 'message' => 'Password changed successfully.']);
    }

    /**
     * GET /client/settings/activity
     * Recent activity log for this client.
     */
    public function activity(Request $request): JsonResponse
    {
        $client = Auth::user()->client;

        $logs = AuditLog::whereIn('user_id', $client->users->pluck('id'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->through(fn($l) => [
                'id'        => $l->id,
                'event'     => $l->event,
                'user'      => $l->user?->only('id', 'name', 'email'),
                'created_at'=> $l->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * DELETE /client/settings/sessions
     * Sign out all other sessions.
     */
    public function signOutOther(): JsonResponse
    {
        Auth::user()->sessions()->where('id', '!=', session()->getId())->delete();

        return response()->json(['success' => true, 'message' => 'Other sessions signed out.']);
    }
}