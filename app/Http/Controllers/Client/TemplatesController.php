<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client\TemplatesController
 *
 * Client admin manages message templates for their team.
 * Templates are scoped to client_id.
 */
class TemplatesController extends Controller
{
    /**
     * GET /client/templates
     */
    public function page(): Response
    {
        $client = Auth::user()->client;
        $templates = MessageTemplate::where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Client/Templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * POST /client/templates
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:text,image,video,document,poll'],
            'body' => ['required', 'string', 'max:4096'],
            'media_url' => ['sometimes', 'nullable', 'url'],
            'media_type' => ['sometimes', 'nullable', 'string'],
            'variables' => ['sometimes', 'array'],
            'description' => ['sometimes', 'string', 'max:500'],
        ]);

        $template = MessageTemplate::create([
            'client_id' => $client->id,
            ...$validated,
        ]);

        AuditLog::record('template.created', $template, [], $validated);

        return response()->json(['success' => true, 'data' => $template], 201);
    }

    /**
     * PATCH /client/templates/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = Auth::user()->client;
        $template = MessageTemplate::where('id', $id)->where('client_id', $client->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'body' => ['sometimes', 'string', 'max:4096'],
            'media_url' => ['sometimes', 'nullable', 'url'],
            'description' => ['sometimes', 'string', 'max:500'],
        ]);

        $old = $template->only(array_keys($validated));
        $template->update($validated);

        AuditLog::record('template.updated', $template, $old, $validated);

        return response()->json(['success' => true, 'data' => $template->fresh()]);
    }

    /**
     * DELETE /client/templates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $client = Auth::user()->client;
        $template = MessageTemplate::where('id', $id)->where('client_id', $client->id)->firstOrFail();

        AuditLog::record('template.deleted', $template, $template->toArray(), []);
        $template->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }

    /**
     * GET /client/templates/preview
     * Preview a template with sample variable substitution.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
            'variables' => ['sometimes', 'array'],
        ]);

        $body = $request->input('body');
        $variables = $request->input('variables', []);

        // Simple substitution: {{name}} → John, {{phone}} → +91...
        $preview = $body;
        foreach ($variables as $key => $value) {
            $preview = str_replace("{{$key}}", $value, $preview);
        }

        // Replace remaining variables with [VARIABLE]
        $preview = preg_replace('/\{\{(\w+)\}\}/', '[$1]', $preview);

        return response()->json(['success' => true, 'preview' => $preview]);
    }
}