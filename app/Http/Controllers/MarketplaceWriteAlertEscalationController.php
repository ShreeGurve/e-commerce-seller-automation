<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteAlertEscalationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceWriteAlertEscalationController extends Controller
{
    public function index(Request $request, MarketplaceWriteAlertEscalationService $service)
    {
        return view('marketplaces.write-alert-escalations', ['rules' => $service->rules($request->user())]);
    }

    public function store(Request $request, MarketplaceWriteAlertEscalationService $service)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(['marketplace_write_attempt', 'marketplace_write_incident'])],
            'severity' => ['required', Rule::in(['critical', 'high', 'warning'])],
            'delay_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'repeat_every_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'max_escalations' => ['required', 'integer', 'min:1', 'max:10'],
            'notify_business_admins' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $service->save($request->user(), $data);

        return back()->with('success', 'Marketplace write alert escalation rule saved.');
    }
}
