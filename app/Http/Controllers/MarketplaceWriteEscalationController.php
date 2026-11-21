<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteEscalationService;
use Illuminate\Http\Request;

class MarketplaceWriteEscalationController extends Controller
{
    public function index(Request $request, MarketplaceWriteEscalationService $service)
    {
        return view('marketplaces.write-escalations', $service->dashboard($request->user()));
    }

    public function saveRule(Request $request, MarketplaceWriteEscalationService $service)
    {
        $service->saveRule($request->user(), $request->all());

        return back()->with('success', 'Escalation rule saved.');
    }
}
