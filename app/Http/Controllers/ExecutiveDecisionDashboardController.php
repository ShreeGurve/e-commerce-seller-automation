<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class ExecutiveDecisionDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'marketplace_id' => ['nullable', 'integer', 'exists:marketplaces,id'],
        ]);
        $filters = [
            'from' => $data['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $data['to'] ?? now()->toDateString(),
            'marketplace_id' => $data['marketplace_id'] ?? null,
        ];
        $report = $dashboard->build($filters);
        $marketplaces = Marketplace::active()->orderBy('name')->get(['id', 'name']);
        return view('executive.decision-dashboard', compact('filters', 'report', 'marketplaces'));
    }
}
