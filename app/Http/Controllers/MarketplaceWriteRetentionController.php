<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteRetentionService;
use Illuminate\Http\Request;

class MarketplaceWriteRetentionController extends Controller
{
    public function __construct(private MarketplaceWriteRetentionService $service) {}

    public function index(Request $request)
    {
        return view('marketplaces.write-retention', $this->service->dashboard($request->user()));
    }

    public function savePolicy(Request $request)
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'alert_before_days' => ['required', 'integer', 'min:1', 'max:365'],
            'auto_archive' => ['nullable', 'boolean'],
            'deletion_requests_enabled' => ['nullable', 'boolean'],
        ]);
        $this->service->savePolicy($request->user(), $data);

        return back()->with('success', 'Evidence retention policy saved.');
    }

    public function placeHold(Request $request, int $evidence)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->placeHold($request->user(), $evidence, $data['reason']);

        return back()->with('success', 'Legal hold placed. Evidence is protected.');
    }

    public function releaseHold(Request $request, int $hold)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->releaseHold($request->user(), $hold, $data['reason']);

        return back()->with('success', 'Legal hold released and recorded.');
    }

    public function requestDeletion(Request $request, int $evidence)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->requestDeletion($request->user(), $evidence, $data['reason']);

        return back()->with('success', 'Deletion request submitted for independent approval. No evidence was deleted.');
    }

    public function approveDeletion(Request $request, int $evidence)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->approveDeletion($request->user(), $evidence, $data['reason']);

        return back()->with('success', 'Deletion request approved. Evidence remains retained pending a separately governed purge process.');
    }
}
