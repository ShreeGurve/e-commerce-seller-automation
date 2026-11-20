<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteSlaService;
use Illuminate\Http\Request;

class MarketplaceWriteSlaController extends Controller
{
    public function __construct(private MarketplaceWriteSlaService $service) {}

    public function index(Request $request)
    {
        return view('marketplaces.write-sla', $this->service->dashboard($request->user()));
    }

    public function savePolicy(Request $request)
    {
        $this->service->savePolicy($request->user(), $request->all());
        return back()->with('success', 'SLA policy saved.');
    }

    public function recordResponse(Request $request, int $incident)
    {
        $this->service->recordResponse($request->user(), $incident);
        return back()->with('success', 'Incident response timestamp recorded.');
    }
}
