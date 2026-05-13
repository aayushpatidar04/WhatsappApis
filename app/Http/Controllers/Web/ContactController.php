<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('User/Contacts');
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $clientId = $this->clientId();
        $query    = Contact::forClient($clientId)
            ->with('groups:id,name')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }
        if ($request->filled('tag'))        $query->withTag($request->tag);
        if ($request->filled('group_id'))   $query->whereHas('groups', fn($q) => $q->where('contact_groups.id', $request->integer('group_id')));
        if ($request->boolean('blocked'))   $query->where('is_blocked', true);
        else                                $query->where('is_blocked', false);

        $contacts = $query->paginate($request->integer('per_page', 25))
            ->through(fn($c) => $this->format($c));

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    // ── Store single ──────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => ['required', 'string', 'max:20'],
            'email'         => ['sometimes', 'nullable', 'email', 'max:255'],
            'tags'          => ['sometimes', 'array'],
            'tags.*'        => ['string', 'max:50'],
            'custom_fields' => ['sometimes', 'array'],
        ]);

        $phone = Contact::normalisePhone($validated['phone']);

        // Check duplicate within client
        $exists = Contact::where('client_id', $this->clientId())->where('phone', $phone)->withTrashed()->first();
        if ($exists) {
            if ($exists->trashed()) {
                $exists->restore();
                $exists->update($validated + ['phone' => $phone]);
                return response()->json(['success' => true, 'data' => $this->format($exists), 'restored' => true]);
            }
            return response()->json(['success' => false, 'message' => 'Contact with this phone already exists.'], 422);
        }

        $contact = Contact::create([
            'client_id'     => $this->clientId(),
            'user_id'       => Auth::id(),
            'name'          => $validated['name'],
            'phone'         => $phone,
            'email'         => $validated['email'] ?? null,
            'tags'          => $validated['tags'] ?? [],
            'custom_fields' => $validated['custom_fields'] ?? [],
        ]);

        return response()->json(['success' => true, 'data' => $this->format($contact)], 201);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $contact   = Contact::forClient($this->clientId())->findOrFail($id);
        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'nullable', 'email'],
            'tags'          => ['sometimes', 'array'],
            'custom_fields' => ['sometimes', 'array'],
            'is_blocked'    => ['sometimes', 'boolean'],
        ]);

        $contact->update($validated);
        return response()->json(['success' => true, 'data' => $this->format($contact->fresh())]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        Contact::forClient($this->clientId())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Contact deleted.']);
    }

    // ── CSV Import ────────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => ['required', 'file', 'mimes:csv,txt', 'max:5120'],  // 5MB
            'group_id'  => ['sometimes', 'nullable', 'integer', 'exists:contact_groups,id'],
        ]);

        $file     = $request->file('file');
        $clientId = $this->clientId();
        $groupId  = $request->integer('group_id') ?: null;

        $handle   = fopen($file->getRealPath(), 'r');
        $headers  = array_map('trim', fgetcsv($handle));  // first row = headers

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        // Map common header variations
        $nameCol  = $this->findCol($headers, ['name', 'full_name', 'contact_name']);
        $phoneCol = $this->findCol($headers, ['phone', 'mobile', 'number', 'phone_number']);

        if ($phoneCol === null) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'CSV must have a "phone" column.'], 422);
        }

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < count($headers)) { $skipped++; continue; }

            $map  = array_combine($headers, $data);
            $phone = Contact::normalisePhone($map[$headers[$phoneCol]] ?? '');
            $name  = $nameCol !== null ? trim($map[$headers[$nameCol]] ?? '') : $phone;

            if (strlen($phone) < 7) { $errors[] = "Row {$row}: invalid phone"; $skipped++; continue; }

            try {
                $contact = Contact::updateOrCreate(
                    ['client_id' => $clientId, 'phone' => $phone],
                    ['name' => $name ?: $phone, 'user_id' => Auth::id()]
                );

                if ($groupId) {
                    $contact->groups()->syncWithoutDetaching([$groupId]);
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$row}: {$e->getMessage()}";
                $skipped++;
            }
        }

        fclose($handle);

        return response()->json([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10),
        ]);
    }

    // ── All tags used by client ───────────────────────────────────────────────

    public function tags(): JsonResponse
    {
        $tags = Contact::forClient($this->clientId())
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json(['success' => true, 'data' => $tags]);
    }

    // ── Groups ────────────────────────────────────────────────────────────────

    public function groups(): JsonResponse
    {
        $groups = ContactGroup::forClient($this->clientId())
            ->withCount('contacts')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'created_at']);

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:500'],
        ]);

        $group = ContactGroup::create(['client_id' => $this->clientId()] + $validated);
        return response()->json(['success' => true, 'data' => $group], 201);
    }

    public function addToGroup(Request $request, int $groupId): JsonResponse
    {
        $group = ContactGroup::forClient($this->clientId())->findOrFail($groupId);
        $request->validate(['contact_ids' => ['required', 'array'], 'contact_ids.*' => ['integer']]);
        $group->contacts()->syncWithoutDetaching($request->contact_ids);
        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function clientId(): int
    {
        $user = Auth::user();
        return $user->isClientAdmin() ? $user->client_id : $user->client_id;
    }

    private function format(Contact $c): array
    {
        return [
            'id'            => $c->id,
            'name'          => $c->name,
            'phone'         => $c->phone,
            'email'         => $c->email,
            'tags'          => $c->tags ?? [],
            'custom_fields' => $c->custom_fields ?? [],
            'is_whatsapp'   => $c->is_whatsapp,
            'is_blocked'    => $c->is_blocked,
            'groups'        => $c->relationLoaded('groups') ? $c->groups->map->only('id', 'name') : [],
            'created_at'    => $c->created_at->toIso8601String(),
        ];
    }

    private function findCol(array $headers, array $candidates): ?int
    {
        foreach ($headers as $i => $h) {
            if (in_array(strtolower(trim($h)), $candidates)) return $i;
        }
        return null;
    }
}