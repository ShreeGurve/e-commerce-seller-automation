<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteIncidentService;
use Illuminate\Http\Request;

class MarketplaceWriteIncidentController extends Controller
{
    public function index(Request $request, MarketplaceWriteIncidentService $service)
    {
        $filters = [
            'status' => strtoupper((string) $request->query('status', '')),
            'severity' => strtoupper((string) $request->query('severity', '')),
        ];

        return view('marketplaces.write-incidents', $service->dashboard((int) $request->user()->business_id, $filters));
    }

    public function store(Request $request, MarketplaceWriteIncidentService $service)
    {
        $data = $request->validate(['attempt_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'], 'severity' => ['required', 'in:LOW,MEDIUM,HIGH,CRITICAL']]);
        $service->openFromAttempt((int) $data['attempt_id'], $request->user(), $data);

        return back()->with('success', 'Marketplace write incident recorded.');
    }

    public function update(Request $request, int $incident, MarketplaceWriteIncidentService $service)
    {
        $data = $request->validate(['status' => ['nullable', 'in:OPEN,INVESTIGATING,RESOLVED,DISMISSED'],
            'severity' => ['nullable', 'in:LOW,MEDIUM,HIGH,CRITICAL'], 'assigned_to' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:5000']]);
        $service->update($incident, $request->user(), $data);

        return back()->with('success', 'Incident updated.');
    }

    public function history(Request $request, int $incident, MarketplaceWriteIncidentService $service)
    {
        return response()->json(['events' => $service->history($incident, $request->user())]);
    }
}
