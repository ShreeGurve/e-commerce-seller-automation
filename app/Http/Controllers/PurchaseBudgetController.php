<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBudget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseBudgetController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        $monthStart = $selectedMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $budget = PurchaseBudget::whereDate('budget_month', $monthStart)->first();

        $actualSpend = (float) DB::table('purchases')
            ->where('status', 'RECEIVED')
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $history = collect();
        for ($i = 1; $i <= 3; $i++) {
            $start = date('Y-m-01', strtotime($monthStart . " -{$i} months"));
            $end = date('Y-m-t', strtotime($start));
            $amount = (float) DB::table('purchases')
                ->where('status', 'RECEIVED')
                ->whereNull('deleted_at')
                ->whereBetween('purchase_date', [$start, $end])
                ->sum('total_amount');
            $history->push(['month' => $start, 'amount' => $amount]);
        }

        $forecast = $history->avg('amount') ?? 0;
        $budgetAmount = $budget ? (float) $budget->budget_amount : 0;
        $variance = $budgetAmount - $actualSpend;
        $utilization = $budgetAmount > 0 ? ($actualSpend / $budgetAmount) * 100 : null;
        $projectedVariance = $budgetAmount - $forecast;

        $recentBudgets = PurchaseBudget::orderByDesc('budget_month')->limit(12)->get();

        return view('purchases.budget-planning', compact(
            'selectedMonth', 'budget', 'actualSpend', 'history', 'forecast',
            'budgetAmount', 'variance', 'utilization', 'projectedVariance', 'recentBudgets'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'budget_month' => ['required', 'date_format:Y-m'],
            'budget_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $month = $validated['budget_month'] . '-01';
        $userId = $request->user()->id;

        PurchaseBudget::updateOrCreate(
            ['budget_month' => $month],
            [
                'budget_amount' => $validated['budget_amount'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => PurchaseBudget::whereDate('budget_month', $month)->value('created_by') ?: $userId,
                'updated_by' => $userId,
            ]
        );

        return redirect()->route('purchase-budgets.index', ['month' => $validated['budget_month']])
            ->with('success', 'Monthly purchase budget saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = PurchaseBudget::orderBy('budget_month')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Budget Month', 'Budget Amount', 'Notes']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->budget_month->format('Y-m'), $row->budget_amount, $row->notes]);
            }
            fclose($out);
        }, 'purchase-budgets.csv', ['Content-Type' => 'text/csv']);
    }
}
