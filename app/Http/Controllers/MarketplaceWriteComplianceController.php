<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteComplianceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceWriteComplianceController extends Controller
{
    public function __construct(private MarketplaceWriteComplianceService $service) {}

    public function index(Request $request)
    {
        return view('marketplaces.write-compliance', $this->service->dashboard($request->user()));
    }

    public function run(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->business_id, 403);
        $this->service->runForBusiness((int) $user->business_id, (int) $user->id);

        return redirect()->route('marketplaces.writes.compliance.index')->with('success', 'Compliance scan completed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $csv = $this->service->export($request->user());

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'marketplace-write-compliance.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
