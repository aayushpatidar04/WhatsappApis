<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $packages = CreditPackage::orderBy('credits')->get();
        return Inertia::render('Admin/Packages', [
            'packages' => $packages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'credits'      => ['required', 'integer', 'min:1'],
            'price'        => ['required', 'numeric', 'min:0'],
            'currency'     => ['required', 'string', 'size:3'],
            'description'  => ['sometimes', 'string', 'max:500'],
            'validity_days'=> ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $package = CreditPackage::create($validated);

        AuditLog::record('package.created', $package, [], $validated);

        return response()->json(['success' => true, 'data' => $package], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $package   = CreditPackage::findOrFail($id);
        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:100'],
            'credits'      => ['sometimes', 'integer', 'min:1'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'description'  => ['sometimes', 'string', 'max:500'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $old = $package->only(array_keys($validated));
        $package->update($validated);

        AuditLog::record('package.updated', $package, $old, $validated);

        return response()->json(['success' => true, 'data' => $package->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $package = CreditPackage::findOrFail($id);
        AuditLog::record('package.deleted', $package, $package->toArray(), []);
        $package->delete();

        return response()->json(['success' => true, 'message' => 'Package deleted.']);
    }
}