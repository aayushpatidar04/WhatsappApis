<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditOrder;
use App\Models\CreditTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RevenueController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Admin/Revenue');
    }

    /**
     * GET /super/revenue/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $orders = CreditOrder::where('status', 'paid')->where('paid_at', '>=', $from);

        $totalRevenue  = (clone $orders)->sum('amount');
        $totalOrders   = (clone $orders)->count();
        $totalCredits  = (clone $orders)->sum('credits');
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // Revenue by gateway
        $byGateway = (clone $orders)
            ->select('gateway', DB::raw('count(*) as orders'), DB::raw('sum(amount) as revenue'))
            ->groupBy('gateway')
            ->get();

        // Revenue by day
        $daily = (clone $orders)
            ->select(DB::raw("date(paid_at) as date"), DB::raw('sum(amount) as revenue'), DB::raw('count(*) as orders'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill gaps
        $dailyMap = $daily->keyBy('date');
        $dailyFilled = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = $dailyMap->get($date);
            $dailyFilled[] = ['date' => $date, 'revenue' => (float)($row?->revenue ?? 0), 'orders' => (int)($row?->orders ?? 0)];
        }

        return response()->json(['success' => true, 'data' => [
            'period_days'   => $days,
            'total_revenue' => (float) $totalRevenue,
            'total_orders'  => $totalOrders,
            'total_credits' => $totalCredits,
            'avg_order'     => $avgOrderValue,
            'by_gateway'    => $byGateway,
            'daily'         => $dailyFilled,
        ]]);
    }

    /**
     * GET /super/revenue/clients
     * Revenue breakdown by client.
     */
    public function byClient(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $from = now()->subDays($days)->startOfDay();

        $rows = CreditOrder::where('status', 'paid')
            ->where('paid_at', '>=', $from)
            ->with('client:id,name')
            ->select('client_id', DB::raw('sum(amount) as revenue'), DB::raw('sum(credits) as credits'), DB::raw('count(*) as orders'))
            ->groupBy('client_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => [
                'client_id'   => $r->client_id,
                'client_name' => $r->client?->name ?? 'Unknown',
                'revenue'     => (float) $r->revenue,
                'credits'     => $r->credits,
                'orders'      => $r->orders,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /super/revenue/audit
     * Audit log with filters.
     */
    public function auditLog(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('event'))   $query->where('event', 'like', "%{$request->event}%");
        if ($request->filled('user_id')) $query->where('user_id', $request->integer('user_id'));

        $logs = $query->paginate($request->integer('per_page', 25))
            ->through(fn($l) => [
                'id'             => $l->id,
                'event'          => $l->event,
                'user'           => $l->user?->only('id', 'name', 'email'),
                'auditable_type' => $l->auditable_type,
                'auditable_id'   => $l->auditable_id,
                'old_values'     => $l->old_values,
                'new_values'     => $l->new_values,
                'ip_address'     => $l->ip_address,
                'created_at'     => $l->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * GET /super/revenue/orders
     * All payment orders with search/filter.
     */
    public function orders(Request $request): JsonResponse
    {
        $orders = CreditOrder::with(['client:id,name', 'package:id,name', 'user:id,name'])
            ->when($request->filled('status'),    fn($q) => $q->where('status', $request->status))
            ->when($request->filled('client_id'), fn($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('gateway'),   fn($q) => $q->where('gateway', $request->gateway))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->through(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'client'       => $o->client?->only('id', 'name'),
                'package'      => $o->package?->only('id', 'name'),
                'user'         => $o->user?->only('id', 'name'),
                'credits'      => $o->credits,
                'amount'       => (float) $o->amount,
                'currency'     => $o->currency,
                'gateway'      => $o->gateway,
                'status'       => $o->status,
                'paid_at'      => $o->paid_at?->toIso8601String(),
                'created_at'   => $o->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $orders]);
    }
}