<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client\ReportsController
 *
 * Client Admin views aggregated reporting for their entire tenant.
 * All data filtered by client_id automatically.
 */
class ReportsController extends Controller
{
    /**
     * GET /client/reports
     */
    public function page(): Response
    {
        $client = Auth::user()->client;

        return Inertia::render('Client/Reports', [
            'client_name' => $client->name,
            'credit_balance' => $client->credit_balance,
        ]);
    }

    /**
     * GET /client/reports/overview
     * High-level metrics for the period.
     */
    public function overview(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        // Message stats
        $messages = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from);

        $totalSent = (clone $messages)->count();
        $totalDelivered = (clone $messages)->where('status', 'delivered')->orWhere('status', 'read')->count();
        $totalRead = (clone $messages)->where('status', 'read')->count();
        $totalFailed = (clone $messages)->where('status', 'failed')->count();

        $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0;
        $readRate = $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 1) : 0;
        $failRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 1) : 0;

        // Credit transactions
        $creditsSpent = CreditTransaction::where('client_id', $client->id)
            ->where('type', 'allocation')
            ->where('created_at', '>=', $from)
            ->sum(DB::raw('abs(credits)'));

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => [
                    'sent' => $totalSent,
                    'delivered' => $totalDelivered,
                    'read' => $totalRead,
                    'failed' => $totalFailed,
                    'delivery_rate' => $deliveryRate,
                    'read_rate' => $readRate,
                    'fail_rate' => $failRate,
                ],
                'credits' => [
                    'spent' => $creditsSpent,
                    'balance' => $client->credit_balance,
                ],
            ]
        ]);
    }

    /**
     * GET /client/reports/daily-volume
     * Daily message volume with gaps filled.
     */
    public function dailyVolume(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $raw = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->select(
                DB::raw("date(created_at) as date"),
                DB::raw("count(*) as total"),
                DB::raw("sum(case when status in ('delivered','read') then 1 else 0 end) as delivered"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill gaps
        $map = $raw->keyBy('date');
        $filled = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row = $map->get($date);
            $filled[] = [
                'date' => $date,
                'total' => (int) ($row?->total ?? 0),
                'delivered' => (int) ($row?->delivered ?? 0),
                'failed' => (int) ($row?->failed ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $filled]);
    }

    /**
     * GET /client/reports/by-instance
     * Message stats per WhatsApp instance.
     */
    public function byInstance(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $data = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->with('instance:id,name,phone_number')
            ->select(
                'instance_id',
                DB::raw("count(*) as total"),
                DB::raw("sum(case when status in ('delivered','read') then 1 else 0 end) as delivered"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            )
            ->groupBy('instance_id')
            ->get()
            ->map(fn($row) => [
                'instance_id' => $row->instance_id,
                'instance_name' => $row->instance?->name ?? 'Unknown',
                'phone' => $row->instance?->phone_number,
                'total' => $row->total,
                'delivered' => $row->delivered,
                'delivery_rate' => $row->total > 0 ? round(($row->delivered / $row->total) * 100) : 0,
            ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /client/reports/type-breakdown
     * Messages by type (text, image, video, etc).
     */
    public function typeBreakdown(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $data = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /client/reports/hourly-heatmap
     * Message volume by hour of day (24-hour heatmap).
     */
    public function hourlyHeatmap(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $raw = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->select(
                DB::raw("hour(created_at) as hour"),
                DB::raw("count(*) as count")
            )
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $heatmap = [];
        for ($h = 0; $h < 24; $h++) {
            $heatmap[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'count' => (int) ($raw->get($h)?->count ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $heatmap]);
    }

    /**
     * GET /client/reports/campaign-stats
     * Campaign performance aggregates.
     */
    public function campaignStats(Request $request): JsonResponse
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $campaigns = \App\Models\Campaign::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->where('status', 'completed')
            ->select(
                DB::raw('count(*) as total'),
                DB::raw('sum(total_recipients) as recipients'),
                DB::raw('sum(delivered_count) as delivered'),
                DB::raw('sum(failed_count) as failed')
            )
            ->first();

        // Compute delivery rate manually
        $recipients = (int) ($campaigns->recipients ?? 0);
        $delivered = (int) ($campaigns->delivered ?? 0);
        $avgRate = $recipients > 0 ? round(($delivered / $recipients) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_campaigns' => (int) ($campaigns->total ?? 0),
                'total_recipients' => $recipients,
                'total_delivered' => $delivered,
                'total_failed' => (int) ($campaigns->failed ?? 0),
                'avg_delivery_rate' => $avgRate,
            ]
        ]);
    }


    /**
     * GET /client/reports/export
     * Download all messages as CSV.
     */
    public function export(Request $request)
    {
        $client = Auth::user()->client;
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $messages = Message::where('client_id', $client->id)
            ->where('created_at', '>=', $from)
            ->orderByDesc('created_at')
            ->get();

        $csv = "Date,Instance,From,To,Type,Status,Message\n";
        foreach ($messages as $m) {
            $csv .= implode(',', [
                '"' . $m->created_at->format('Y-m-d H:i:s') . '"',
                '"' . ($m->instance?->name ?? 'N/A') . '"',
                $m->sender_number,
                $m->recipient_number,
                $m->type,
                $m->status,
                '"' . str_replace('"', '""', substr($m->body ?? '', 0, 100)) . '"',
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=messages_' . now()->format('Ymd_His') . '.csv');
    }
}