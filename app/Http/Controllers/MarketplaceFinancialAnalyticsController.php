<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Services\MarketplaceFinancialAnalyticsService;
use Illuminate\Http\Request;

class MarketplaceFinancialAnalyticsController extends Controller
{
    public function index(Request $request, MarketplaceFinancialAnalyticsService $analytics)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'marketplace_id' => ['nullable', 'integer', 'exists:marketplaces,id'],
        ]);
        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= now()->toDateString();

        return view('finance.marketplace-analytics', [
            'report' => $analytics->dashboard($filters),
            'marketplaces' => Marketplace::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
