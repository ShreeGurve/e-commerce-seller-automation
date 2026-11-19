<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceBusinessIntelligenceService;
use Illuminate\Http\Request;

class MarketplaceBusinessIntelligenceController extends Controller
{
    public function index(Request $request, MarketplaceBusinessIntelligenceService $intelligence)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'marketplace_id' => ['nullable', 'integer', 'exists:marketplaces,id'],
            'low_margin_threshold' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= now()->toDateString();

        return view('analytics.marketplace-business-intelligence', [
            'report' => $intelligence->dashboard($filters),
        ]);
    }
}
