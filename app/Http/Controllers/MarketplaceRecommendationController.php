<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceRecommendationService;
use Illuminate\Http\Request;

class MarketplaceRecommendationController extends Controller
{
    public function index(Request $request, MarketplaceRecommendationService $service)
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,14,30,60,90'],
            'low_stock_cover' => ['nullable', 'integer', 'between:1,60'],
            'return_threshold' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        return view('analytics.marketplace-recommendations', [
            'report' => $service->dashboard($filters),
        ]);
    }
}
