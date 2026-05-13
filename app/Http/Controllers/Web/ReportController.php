<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\WhatsappInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('User/Reports');
    }

    /**
     * GET /dashboard/reports/overview
     * Main stats: messages, delivery, instances, campaigns.
     */
    public function overview(Request $request): JsonResponse
    {
        $user   = Auth::user();
        $days   = $request->integer('days', 30);
        $from   = now()->subDays($days)->startOfDay();

        $msgBase  = $this->msgQuery($user)->where('created_at', '>=', $from);
        $campBase = $this->campQuery($user)->where('created_at', '>=', $from);

        $totalSent      = (clone $msgBase)->outbound()->count();
        $totalReceived  = (clone $msgBase)->inbound()->count();
        $totalDelivered = (clone $msgBase)->outbound()->whereIn('status', ['delivered', 'read'])->count();
        $totalRead      = (clone $msgBase)->outbound()->where('status', 'read')->count();
        $totalFailed    = (clone $msgBase)->outbound()->where('status', 'failed')->count();

        return response()->json(['success' => true, 'data' => [
            'period_days'    => $days,
            'messages'       => [
                'sent'         => $totalSent,
                'received'     => $totalReceived,
                'delivered'    => $totalDelivered,
                'read'         => $totalRead,
                'failed'       => $totalFailed,
                'delivery_rate'=> $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0,
                'read_rate'    => $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 1) : 0,
                'fail_rate'    => $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 1) : 0,
            ],
            'campaigns'      => [
                'total'        => (clone $campBase)->count(),
                'completed'    => (clone $campBase)->where('status', 'completed')->count(),
                'running'      => (clone $campBase)->where('status', 'running')->count(),
            ],
            'active_instances' => WhatsappInstance::where('status', 'active')
                ->when($user->isClientAdmin(), fn($q) => $q->where('client_id', $user->client_id))
                ->when($user->isUser(), fn($q) => $q->where('owner_id', $user->id)->where('owner_type', 'user'))
                ->count(),
        ]]);
    }

    /**
     * GET /dashboard/reports/daily-volume
     * Daily message counts for the line chart.
     */
    public function dailyVolume(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = $this->msgQuery($user)
            ->where('created_at', '>=', $from)
            ->outbound()
            ->select(
                DB::raw("date(created_at) as date"),
                DB::raw("count(*) as total"),
                DB::raw("sum(case when status in ('delivered','read') then 1 else 0 end) as delivered"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill gaps with zeros
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = $rows->firstWhere('date', $date);
            $result[] = [
                'date'      => $date,
                'total'     => $row?->total ?? 0,
                'delivered' => $row?->delivered ?? 0,
                'failed'    => $row?->failed ?? 0,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /dashboard/reports/by-instance
     * Per-instance message breakdown.
     */
    public function byInstance(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $rows = $this->msgQuery($user)
            ->where('created_at', '>=', $from)
            ->outbound()
            ->select(
                'instance_id',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status in ('delivered','read') then 1 else 0 end) as delivered"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
            )
            ->groupBy('instance_id')
            ->with('instance:id,name,phone_number')
            ->get()
            ->map(fn($r) => [
                'instance_id'   => $r->instance_id,
                'instance_name' => $r->instance?->name ?? 'Unknown',
                'phone'         => $r->instance?->phone_number,
                'total'         => $r->total,
                'delivered'     => $r->delivered,
                'failed'        => $r->failed,
                'delivery_rate' => $r->total > 0 ? round(($r->delivered / $r->total) * 100, 1) : 0,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /dashboard/reports/message-type-breakdown
     * Pie chart data: text vs image vs video etc.
     */
    public function typeBreakdown(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $rows = $this->msgQuery($user)
            ->where('created_at', '>=', $from)
            ->outbound()
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['type' => $r->type, 'count' => $r->count]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /dashboard/reports/hourly-heatmap
     * Message counts by hour of day.
     */
    public function hourlyHeatmap(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $rows = $this->msgQuery($user)
            ->where('created_at', '>=', $from)
            ->outbound()
            ->select(
                DB::raw('strftime("%H", created_at) as hour'),
                DB::raw('count(*) as count'),
            )
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $key = str_pad($h, 2, '0', STR_PAD_LEFT);
            $result[] = ['hour' => $h, 'label' => "{$key}:00", 'count' => $rows->get($key)?->count ?? 0];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /dashboard/reports/campaign-funnel
     * Funnel: total → sent → delivered → read for all campaigns.
     */
    public function campaignFunnel(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);

        $campaigns = $this->campQuery($user)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $total     = $campaigns->sum('total_recipients');
        $sent      = $campaigns->sum('sent_count');
        $delivered = $campaigns->sum('delivered_count');
        $read      = $campaigns->sum('read_count');

        return response()->json(['success' => true, 'data' => [
            ['stage' => 'Total',     'count' => $total],
            ['stage' => 'Sent',      'count' => $sent],
            ['stage' => 'Delivered', 'count' => $delivered],
            ['stage' => 'Read',      'count' => $read],
        ]]);
    }

    /**
     * GET /dashboard/reports/export
     * Download message log as CSV.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user  = Auth::user();
        $days  = $request->integer('days', 30);
        $from  = now()->subDays($days)->startOfDay();

        $filename = 'messages-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($user, $from) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Direction', 'Phone', 'Type', 'Body', 'Status', 'Instance', 'Sent At', 'Delivered At', 'Read At', 'Created At']);

            $this->msgQuery($user)
                ->where('created_at', '>=', $from)
                ->with('instance:id,name')
                ->orderByDesc('created_at')
                ->chunk(500, function ($messages) use ($handle) {
                    foreach ($messages as $m) {
                        fputcsv($handle, [
                            $m->id,
                            $m->direction,
                            $m->phone,
                            $m->type,
                            substr($m->body ?? '', 0, 100),
                            $m->status,
                            $m->instance?->name,
                            $m->sent_at?->format('Y-m-d H:i:s'),
                            $m->delivered_at?->format('Y-m-d H:i:s'),
                            $m->read_at?->format('Y-m-d H:i:s'),
                            $m->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function msgQuery($user)
    {
        return Message::when($user->isSuperAdmin(), fn($q) => $q)
            ->when($user->isClientAdmin(), fn($q) => $q->where('client_id', $user->client_id))
            ->when($user->isUser(), fn($q) => $q->where('user_id', $user->id));
    }

    private function campQuery($user)
    {
        return Campaign::when($user->isSuperAdmin(), fn($q) => $q)
            ->when($user->isClientAdmin(), fn($q) => $q->forClient($user->client_id))
            ->when($user->isUser(), fn($q) => $q->forUser($user->id));
    }
}