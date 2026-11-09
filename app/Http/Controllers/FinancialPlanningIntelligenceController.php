<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderProfit;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Settlement;
use App\Models\SupplierInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialPlanningIntelligenceController extends Controller
{
    private const SALES_STATUSES = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'];

    public function cashForecast(Request $request)
    {
        $filters = $request->validate([
            'lookback_days' => 'nullable|integer|in:30,60,90',
            'horizon_days' => 'nullable|integer|in:30,60,90',
            'collection_rate' => 'nullable|numeric|between:0,100',
            'expense_buffer' => 'nullable|numeric|between:0,100',
        ]);
        $lookback = (int) ($filters['lookback_days'] ?? 60);
        $horizon = (int) ($filters['horizon_days'] ?? 30);
        $collectionRate = (float) ($filters['collection_rate'] ?? 80);
        $expenseBuffer = (float) ($filters['expense_buffer'] ?? 10);
        $from = now()->subDays($lookback - 1)->startOfDay();
        $to = now()->endOfDay();

        $sales = Order::query()->whereBetween('order_date', [$from, $to])
            ->whereIn('status', self::SALES_STATUSES)->where('source', '<>', 'TEST')
            ->selectRaw('COALESCE(SUM(total_amount),0) revenue, COUNT(*) order_count')->first();
        $expenseTotal = (float) Expense::query()->whereBetween('expense_date', [$from->toDateString(), now()->toDateString()])->sum('amount');
        $paidSupplier = (float) SupplierInvoicePayment::query()->where('status', 'CONFIRMED')
            ->whereBetween('payment_date', [$from->toDateString(), now()->toDateString()])->sum('amount');
        $dailySales = (float) $sales->revenue / max(1, $lookback);
        $dailyExpenses = $expenseTotal / max(1, $lookback);
        $dailySupplierPayments = $paidSupplier / max(1, $lookback);
        $projectedRevenue = $dailySales * $horizon;
        $expectedCollections = $projectedRevenue * ($collectionRate / 100);
        $projectedExpenses = $dailyExpenses * $horizon * (1 + $expenseBuffer / 100);
        $projectedSupplierPayments = $dailySupplierPayments * $horizon;

        $openPurchases = Purchase::query()->whereIn('status', ['ORDERED', 'RECEIVED'])->whereNull('deleted_at')->get();
        $openPayables = (float) $openPurchases->sum(fn ($p) => max(0, (float) $p->total_amount - (float) $p->amount_paid));
        $duePayables = (float) $openPurchases->filter(fn ($p) => ! $p->expected_delivery_date || $p->expected_delivery_date->lte(now()->addDays($horizon)->endOfDay()))
            ->sum(fn ($p) => max(0, (float) $p->total_amount - (float) $p->amount_paid));
        $netOperatingEstimate = $expectedCollections - $projectedExpenses - $projectedSupplierPayments - $duePayables;

        return view('finance.operations.cash-forecast', compact('lookback', 'horizon', 'collectionRate', 'expenseBuffer', 'sales', 'expenseTotal', 'paidSupplier', 'projectedRevenue', 'expectedCollections', 'projectedExpenses', 'projectedSupplierPayments', 'openPayables', 'duePayables', 'netOperatingEstimate'));
    }

    public function workingCapital(Request $request)
    {
        $filters = $request->validate(['period_days' => 'nullable|integer|in:30,60,90']);
        $period = (int) ($filters['period_days'] ?? 30);
        $since = now()->subDays($period)->startOfDay();

        $inventoryValue = 0.0;
        $inventoryUnits = 0;
        $inventoryRows = Inventory::query()->with(['product.currentCosts'])->get();
        foreach ($inventoryRows as $row) {
            $qty = max(0, (int) $row->available_qty) + max(0, (int) $row->reserved_qty);
            $inventoryUnits += $qty;
            $inventoryValue += $qty * (float) ($row->product?->totalUnitCost() ?? 0);
        }
        $purchasePayables = (float) Purchase::query()->whereIn('status', ['ORDERED', 'RECEIVED'])->whereNull('deleted_at')
            ->get()->sum(fn ($p) => max(0, (float) $p->total_amount - (float) $p->amount_paid));
        $recentSales = (float) Order::query()->whereBetween('order_date', [$since, now()->endOfDay()])
            ->whereIn('status', self::SALES_STATUSES)->where('source', '<>', 'TEST')->sum('total_amount');
        $recentProfit = (float) OrderProfit::query()->whereBetween('order_date', [$since->toDateString(), now()->toDateString()])
            ->whereHas('order', fn ($q) => $q->where('source', '<>', 'TEST'))
            ->selectRaw('COALESCE(SUM(COALESCE(actual_profit, estimated_profit)),0) as total')->value('total');
        $recentExpenses = (float) Expense::query()->whereBetween('expense_date', [$since->toDateString(), now()->toDateString()])->sum('amount');
        $workingCapitalProxy = $inventoryValue - $purchasePayables;

        return view('finance.operations.working-capital', compact('period', 'inventoryValue', 'inventoryUnits', 'purchasePayables', 'recentSales', 'recentProfit', 'recentExpenses', 'workingCapitalProxy'));
    }

    public function financialHealth(Request $request)
    {
        $filters = $request->validate(['period_days' => 'nullable|integer|in:30,60,90']);
        $period = (int) ($filters['period_days'] ?? 30);
        $from = now()->subDays($period - 1)->startOfDay();
        $to = now()->endOfDay();
        $sales = (float) Order::query()->whereBetween('order_date', [$from, $to])->whereIn('status', self::SALES_STATUSES)
            ->where('source', '<>', 'TEST')->sum('total_amount');
        $profit = (float) OrderProfit::query()->whereBetween('order_date', [$from->toDateString(), now()->toDateString()])
            ->whereHas('order', fn ($q) => $q->where('source', '<>', 'TEST'))
            ->selectRaw('COALESCE(SUM(COALESCE(actual_profit, estimated_profit)),0) as total')->value('total');
        $expenses = (float) Expense::query()->whereBetween('expense_date', [$from->toDateString(), now()->toDateString()])->sum('amount');
        $payables = (float) Purchase::query()->whereIn('status', ['ORDERED', 'RECEIVED'])->whereNull('deleted_at')->get()
            ->sum(fn ($p) => max(0, (float) $p->total_amount - (float) $p->amount_paid));
        $settlementCount = Settlement::query()->whereBetween('period_end', [$from->toDateString(), now()->toDateString()])->count();
        $settlementValue = (float) Settlement::query()->whereBetween('period_end', [$from->toDateString(), now()->toDateString()])->sum('total_amount');
        $profitMargin = $sales > 0 ? ($profit / $sales) * 100 : 0;
        $expenseRatio = $sales > 0 ? ($expenses / $sales) * 100 : 0;
        $signals = [];
        if ($sales <= 0) $signals[] = ['warning', 'No qualifying sales recorded in the selected period.'];
        if ($profit < 0) $signals[] = ['danger', 'Recorded order profit is negative for the selected period.'];
        if ($payables > $sales) $signals[] = ['warning', 'Open supplier payables exceed selected-period sales; review cash commitments.'];
        if ($settlementCount === 0) $signals[] = ['info', 'No settlement batches have an ending date in the selected period.'];
        if (! $signals) $signals[] = ['success', 'No threshold-based warning was identified from the available records.'];

        return view('finance.operations.financial-health', compact('period', 'sales', 'profit', 'expenses', 'payables', 'settlementCount', 'settlementValue', 'profitMargin', 'expenseRatio', 'signals'));
    }
}
