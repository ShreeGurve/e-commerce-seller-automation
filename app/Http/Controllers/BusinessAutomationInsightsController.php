<?php

namespace App\Http\Controllers;

use App\Models\BusinessAutomationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessAutomationInsightsController extends Controller
{
    private const METRICS = [
        'sales_value' => ['label' => 'Order sales value', 'unit' => 'currency'],
        'orders' => ['label' => 'Non-test orders', 'unit' => 'number'],
        'fulfilled_orders' => ['label' => 'Delivered / closed orders', 'unit' => 'number'],
        'approved_requisitions' => ['label' => 'Approved requisitions', 'unit' => 'number'],
        'supplier_payments' => ['label' => 'Confirmed supplier payments', 'unit' => 'currency'],
    ];

    private function actuals(string $start, string $end): array
    {
        $orders = Schema::hasTable('orders')
            ? DB::table('orders')->whereBetween('order_date', [$start.' 00:00:00', $end.' 23:59:59'])
                ->where(function ($q) { $q->whereNull('source')->orWhere('source', '<>', 'TEST'); })
                ->whereNull('deleted_at')
            : null;
        $eligible = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'];
        $sales = $orders ? (float) (clone $orders)->whereIn('status', $eligible)->sum('total_amount') : 0.0;
        $count = $orders ? (int) (clone $orders)->whereIn('status', $eligible)->count() : 0;
        $fulfilled = $orders ? (int) (clone $orders)->whereIn('status', ['DELIVERED', 'CLOSED'])->count() : 0;
        $requisitions = Schema::hasTable('procurement_requisitions')
            ? (int) DB::table('procurement_requisitions')->whereBetween('approved_at', [$start.' 00:00:00', $end.' 23:59:59'])->whereIn('status', ['APPROVED', 'AWARDED'])->count() : 0;
        $payments = Schema::hasTable('supplier_invoice_payments')
            ? (float) DB::table('supplier_invoice_payments')->whereBetween('payment_date', [$start, $end])->where('status', 'CONFIRMED')->sum('amount') : 0.0;
        return ['sales_value' => $sales, 'orders' => $count, 'fulfilled_orders' => $fulfilled, 'approved_requisitions' => $requisitions, 'supplier_payments' => $payments];
    }

    /** Package 63: manually view/download a daily executive digest; it does not send email. */
    public function dailyDigest(Request $request)
    {
        $date = $request->query('date', now()->subDay()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = now()->subDay()->toDateString();
        $actuals = $this->actuals($date, $date);
        $events = Schema::hasTable('business_automation_events')
            ? BusinessAutomationEvent::whereDate('created_at', $date)->orderByRaw("FIELD(severity, 'CRITICAL', 'HIGH', 'WARNING', 'INFO')")->latest()->limit(100)->get()
            : collect();
        $openCount = Schema::hasTable('business_automation_events') ? BusinessAutomationEvent::where('status', 'OPEN')->count() : 0;
        return view('business-automation.daily-digest', compact('date', 'actuals', 'events', 'openCount'));
    }

    /** Package 64: due-date SLA and overdue queue for existing assigned alerts. */
    public function alertSla(Request $request)
    {
        $filter = $request->query('filter', 'overdue');
        if (!in_array($filter, ['overdue', 'due-today', 'upcoming', 'all'], true)) $filter = 'overdue';
        $events = Schema::hasTable('business_automation_events')
            ? BusinessAutomationEvent::with(['assignee', 'creator'])->where('status', 'OPEN')->whereNotNull('due_at')
                ->when($filter === 'overdue', fn ($q) => $q->where('due_at', '<', now()))
                ->when($filter === 'due-today', fn ($q) => $q->whereDate('due_at', today()))
                ->when($filter === 'upcoming', fn ($q) => $q->where('due_at', '>', now())->whereDate('due_at', '>', today()))
                ->orderBy('due_at')->limit(300)->get()
            : collect();
        $counts = [
            'overdue' => Schema::hasTable('business_automation_events') ? BusinessAutomationEvent::where('status', 'OPEN')->whereNotNull('due_at')->where('due_at', '<', now())->count() : 0,
            'today' => Schema::hasTable('business_automation_events') ? BusinessAutomationEvent::where('status', 'OPEN')->whereDate('due_at', today())->count() : 0,
            'unassigned' => Schema::hasTable('business_automation_events') ? BusinessAutomationEvent::where('status', 'OPEN')->whereNull('assigned_to')->count() : 0,
        ];
        return view('business-automation.alert-sla', compact('events', 'filter', 'counts'));
    }

    /** Package 65: transparent six-month straight-line trend estimate, not a guaranteed forecast. */
    public function kpiForecast(Request $request)
    {
        $metric = $request->query('metric', 'sales_value');
        if (!array_key_exists($metric, self::METRICS)) $metric = 'sales_value';
        $months = [];
        $base = now()->startOfMonth()->subMonths(5);
        for ($i = 0; $i < 6; $i++) {
            $month = $base->copy()->addMonths($i);
            $months[] = ['label' => $month->format('M Y'), 'month' => $month->format('Y-m'), 'value' => $this->actuals($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())[$metric]];
        }
        $n = count($months); $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($months as $i => $point) { $x = $i + 1; $y = (float) $point['value']; $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x; }
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
        $next = [];
        for ($i = 1; $i <= 3; $i++) {
            $month = now()->startOfMonth()->addMonths($i - 1);
            $raw = $intercept + ($slope * ($n + $i));
            $next[] = ['label' => $month->format('M Y'), 'value' => round(max(0, $raw), 2)];
        }
        return view('business-automation.kpi-forecast', ['metric' => $metric, 'meta' => self::METRICS[$metric], 'history' => $months, 'forecast' => $next, 'slope' => round($slope, 2)]);
    }
}
