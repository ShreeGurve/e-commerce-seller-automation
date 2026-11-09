<?php

namespace App\Http\Controllers;

use App\Models\BusinessKpiTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiTrendController extends Controller
{
    private const METRICS = [
        'sales_value' => ['label' => 'Order sales value', 'unit' => 'currency'],
        'orders' => ['label' => 'Non-test orders', 'unit' => 'number'],
        'fulfilled_orders' => ['label' => 'Delivered / closed orders', 'unit' => 'number'],
        'approved_requisitions' => ['label' => 'Approved / awarded requisitions', 'unit' => 'number'],
        'supplier_payments' => ['label' => 'Confirmed supplier payments', 'unit' => 'currency'],
    ];

    public function index(Request $request)
    {
        $endMonth = $request->query('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $endMonth) || !checkdate((int) substr($endMonth, 5, 2), 1, (int) substr($endMonth, 0, 4))) $endMonth = now()->format('Y-m');
        $months = [];
        $last = \Carbon\Carbon::createFromFormat('Y-m-d', $endMonth . '-01')->startOfMonth();
        for ($i = 5; $i >= 0; $i--) $months[] = $last->copy()->subMonths($i);
        $targets = BusinessKpiTarget::whereBetween('target_month', [$months[0]->toDateString(), $months[5]->toDateString()])->get()->groupBy(fn ($t) => $t->target_month->format('Y-m'));
        $series = [];
        foreach (self::METRICS as $key => $meta) {
            $points = [];
            foreach ($months as $month) {
                $start = $month->toDateString(); $end = $month->copy()->endOfMonth()->toDateString();
                $actual = $this->actual($key, $start, $end);
                $target = (float) optional(($targets[$month->format('Y-m')] ?? collect())->firstWhere('metric_key', $key))->target_value;
                $points[] = ['month' => $month->format('M Y'), 'actual' => $actual, 'target' => $target, 'variance' => $target > 0 ? round($actual - $target, 2) : null, 'attainment' => $target > 0 ? round(($actual / $target) * 100, 1) : null];
            }
            $series[] = ['key' => $key, 'label' => $meta['label'], 'unit' => $meta['unit'], 'points' => $points];
        }
        return view('kpi.trends', compact('endMonth', 'series'));
    }

    private function actual(string $key, string $start, string $end): float
    {
        if ($key === 'sales_value' || $key === 'orders' || $key === 'fulfilled_orders') {
            if (!Schema::hasTable('orders')) return 0;
            $q = DB::table('orders')->whereBetween('order_date', [$start . ' 00:00:00', $end . ' 23:59:59'])->whereNull('deleted_at')->where(function ($q) { $q->whereNull('source')->orWhere('source', '<>', 'TEST'); });
            if ($key === 'sales_value') return (float) (clone $q)->whereIn('status', ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'])->sum('total_amount');
            if ($key === 'orders') return (float) (clone $q)->whereIn('status', ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'])->count();
            return (float) $q->whereIn('status', ['DELIVERED', 'CLOSED'])->count();
        }
        if ($key === 'approved_requisitions') return Schema::hasTable('procurement_requisitions') ? (float) DB::table('procurement_requisitions')->whereBetween('approved_at', [$start . ' 00:00:00', $end . ' 23:59:59'])->whereIn('status', ['APPROVED', 'AWARDED'])->count() : 0;
        if ($key === 'supplier_payments') return Schema::hasTable('supplier_invoice_payments') ? (float) DB::table('supplier_invoice_payments')->whereBetween('payment_date', [$start, $end])->where('status', 'CONFIRMED')->sum('amount') : 0;
        return 0;
    }
}
