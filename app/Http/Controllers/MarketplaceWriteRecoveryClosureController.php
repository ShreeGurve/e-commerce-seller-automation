<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteRecoveryClosureService;
use Illuminate\Http\Request;

class MarketplaceWriteRecoveryClosureController extends Controller
{
    public function request(Request $request, int $case, MarketplaceWriteRecoveryClosureService $service)
    {
        $data = $request->validate(['resolution_summary' => ['required', 'string', 'max:5000']]);
        $id = $service->request($request->user(), $case, $data);

        return back()->with('success', "Closure review #{$id} submitted for independent approval.");
    }

    public function review(Request $request, int $review, MarketplaceWriteRecoveryClosureService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);
        $service->review($request->user(), $review, $data);

        return back()->with('success', 'Recovery closure review recorded.');
    }
}
