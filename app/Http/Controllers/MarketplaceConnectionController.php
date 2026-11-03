<?php

namespace App\Http\Controllers;

use App\Marketplaces\Sync\ConnectorReadiness;
use App\Models\Marketplace;

class MarketplaceConnectionController extends Controller
{
    public function index(ConnectorReadiness $readiness)
    {
        return view('marketplaces.connections', [
            'status' => $readiness->status(),
            'marketplaces' => Marketplace::active()->get(),
        ]);
    }
}
