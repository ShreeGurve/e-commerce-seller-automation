<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MarketplaceWriteVerificationController extends Controller
{
    public function index(Request $request, MarketplaceWriteVerificationService $service)
    {
        return view('marketplaces.write-verification', $service->dashboard((int) $request->user()->business_id));
    }

    public function history(Request $request, int $attempt, MarketplaceWriteVerificationService $service)
    {
        try {
            $events = $service->history($attempt, $request->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['attempt' => $e->getMessage()]);
        }

        return view('marketplaces.write-verification-history', compact('events', 'attempt'));
    }

    public function store(Request $request, int $attempt, MarketplaceWriteVerificationService $service)
    {
        $data = $request->validate([
            'status' => ['required', 'in:PENDING,LIVE_CONFIRMED,NOT_VISIBLE,ACTION_REQUIRED,REJECTED'],
            'verification_note' => ['required', 'string', 'min:10', 'max:2000'],
            'issue_code' => ['nullable', 'string', 'max:100'],
            'external_submission_id' => ['nullable', 'string', 'max:150'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
        ]);

        try {
            $result = $service->record($attempt, $request->user(), $data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['attempt' => $e->getMessage()]);
        }

        return back()->with('success', 'Verification outcome recorded as '.$result->status.'. The submission itself was not changed or resubmitted.');
    }
}
