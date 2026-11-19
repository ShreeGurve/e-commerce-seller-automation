<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryAlert;
use App\Models\MarketplaceInventoryAutomationSetting;
use Illuminate\Http\Request;

class MarketplaceInventoryAutomationController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $connections = MarketplaceConnection::with('marketplace')->where('business_id', $businessId)->latest()->get();
        $settings = MarketplaceInventoryAutomationSetting::with('connection.marketplace')->where('business_id', $businessId)->get()->keyBy('marketplace_connection_id');
        $alerts = MarketplaceInventoryAlert::with('connection.marketplace')->where('business_id', $businessId)->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->latest('last_seen_at')->paginate(20);

        return view('marketplaces.inventory-automation.index', compact('connections', 'settings', 'alerts'));
    }

    public function update(Request $request, MarketplaceConnection $connection)
    {
        abort_unless((int) $connection->business_id === (int) $request->user()->business_id, 404);
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'interval_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        MarketplaceInventoryAutomationSetting::updateOrCreate(
            ['marketplace_connection_id' => $connection->id],
            [
                'business_id' => $connection->business_id,
                'enabled' => (bool) ($data['enabled'] ?? false),
                'interval_hours' => $data['interval_hours'],
                'low_stock_threshold' => $data['low_stock_threshold'],
                'next_run_at' => !empty($data['enabled']) ? now() : null,
            ]
        );

        return back()->with('success', 'Inventory automation settings saved.');
    }

    public function acknowledge(Request $request, MarketplaceInventoryAlert $alert)
    {
        abort_unless((int) $alert->business_id === (int) $request->user()->business_id, 404);
        if ($alert->status === 'OPEN') {
            $alert->status = 'ACKNOWLEDGED';
            $alert->save();
        }

        return back()->with('success', 'Alert acknowledged.');
    }
}
