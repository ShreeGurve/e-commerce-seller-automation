<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteGovernanceAlertService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceWriteAlertPreferenceController extends Controller
{
    public function edit(Request $request, MarketplaceWriteGovernanceAlertService $service)
    {
        abort_unless($request->user()->hasPermission('listings.manage'), Response::HTTP_FORBIDDEN);

        return view('marketplaces.write-alert-preferences', [
            'preferences' => $service->preferences($request->user()),
        ]);
    }

    public function update(Request $request, MarketplaceWriteGovernanceAlertService $service)
    {
        abort_unless($request->user()->hasPermission('listings.manage'), Response::HTTP_FORBIDDEN);
        $data = $request->validate([
            'attempt_alerts' => ['sometimes', 'boolean'],
            'incident_alerts' => ['sometimes', 'boolean'],
            'critical_alerts' => ['sometimes', 'boolean'],
            'high_alerts' => ['sometimes', 'boolean'],
            'warning_alerts' => ['sometimes', 'boolean'],
            'stale_after_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ]);
        $service->savePreferences($request->user(), $data);

        return redirect()->route('marketplaces.writes.alerts.preferences.edit')
            ->with('success', 'Marketplace write alert preferences saved.');
    }
}
