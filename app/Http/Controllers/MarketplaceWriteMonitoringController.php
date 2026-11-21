<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteExecutionMonitoringService;
use Illuminate\Http\Request;

class MarketplaceWriteMonitoringController extends Controller
{
    public function index(Request $request, MarketplaceWriteExecutionMonitoringService $service)
    {
        return view('marketplaces.write-monitoring', $service->dashboard((int) $request->user()->business_id));
    }

    public function scan(Request $request, MarketplaceWriteExecutionMonitoringService $service)
    {
        $result = $service->scan((int) $request->user()->business_id);

        return back()->with('success', "Scan complete: {$result['detected']} attempts checked; {$result['created']} new incidents created.");
    }

    public function acknowledge(Request $request, int $incident, MarketplaceWriteExecutionMonitoringService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->acknowledge($incident, (int) $request->user()->business_id, (int) $request->user()->id, $data['note']);

        return back()->with('success', 'Incident moved to In Progress.');
    }

    public function resolve(Request $request, int $incident, MarketplaceWriteExecutionMonitoringService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:2000']]);
        $service->resolve($incident, (int) $request->user()->business_id, (int) $request->user()->id, $data['note']);

        return back()->with('success', 'Incident marked resolved. The marketplace attempt itself was not retried or modified.');
    }
}
