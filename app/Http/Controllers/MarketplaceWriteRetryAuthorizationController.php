<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteRetryAuthorizationService;
use Illuminate\Http\Request;

class MarketplaceWriteRetryAuthorizationController extends Controller
{
    public function index(Request $request, MarketplaceWriteRetryAuthorizationService $service)
    {
        return view('marketplaces.write-retry-authorizations', $service->dashboard((int) $request->user()->business_id));
    }

    public function store(Request $request, MarketplaceWriteRetryAuthorizationService $service)
    {
        $data = $request->validate([
            'incident_id' => ['required', 'integer', 'min:1'],
            'verification_outcome' => ['required', 'in:NOT_APPLIED_CONFIRMED'],
            'evidence_reference' => ['required', 'string', 'max:500'],
            'request_reason' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $service->request((int) $request->user()->business_id, (int) $request->user()->id, $data);

        return back()->with('success', 'Retry authorization submitted for independent review. No marketplace request was sent.');
    }

    public function review(Request $request, int $authorization, MarketplaceWriteRetryAuthorizationService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $service->review((int) $request->user()->business_id, $authorization, (int) $request->user()->id, $data['decision'], $data['review_note']);

        return back()->with('success', 'Retry authorization review recorded. Approval permits preparation of a fresh attempt; it does not submit one.');
    }
}
