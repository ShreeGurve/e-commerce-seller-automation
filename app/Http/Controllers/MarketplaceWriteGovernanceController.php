<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteGovernanceService;
use Illuminate\Http\Request;

class MarketplaceWriteGovernanceController extends Controller
{
    public function __construct(private MarketplaceWriteGovernanceService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->dashboard($request->user());
        $data['emergencyControl'] = \Illuminate\Support\Facades\DB::table('marketplace_write_emergency_controls')
            ->where('business_id', $request->user()->business_id)->first();

        return view('marketplaces.write-governance', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'writes_enabled' => ['sometimes', 'nullable', 'boolean'],
            'inventory_auto_sync' => ['sometimes', 'nullable', 'boolean'],
            'require_second_person' => ['sometimes', 'nullable', 'boolean'],
            'require_evidence_reference' => ['sometimes', 'nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $changes = collect($data)->except('reason')->all();
        $this->service->requestChanges($request->user(), $changes, $data['reason']);

        return redirect()->route('marketplaces.writes.governance.index')->with('success', 'Governance change request submitted for independent review.');
    }

    public function review(Request $request, int $governanceRequest)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $this->service->review($request->user(), $governanceRequest, $data['decision'], $data['reason']);

        return redirect()->route('marketplaces.writes.governance.index')->with('success', 'Governance request reviewed.');
    }
}
