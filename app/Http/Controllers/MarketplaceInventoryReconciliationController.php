<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryReconciliationItem;
use App\Models\MarketplaceInventoryReconciliationRun;
use App\Services\MarketplaceInventoryReconciliationService;
use Illuminate\Http\Request;

class MarketplaceInventoryReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $connections = MarketplaceConnection::with('marketplace')
            ->where('business_id', $businessId)->latest()->get();
        $runs = MarketplaceInventoryReconciliationRun::with('connection.marketplace')
            ->where('business_id', $businessId)->latest()->paginate(10);
        $selectedRun = null;
        $items = collect();
        if ($request->filled('run')) {
            $selectedRun = MarketplaceInventoryReconciliationRun::where('business_id', $businessId)->findOrFail($request->integer('run'));
        } else {
            $selectedRun = MarketplaceInventoryReconciliationRun::where('business_id', $businessId)->latest()->first();
        }
        if ($selectedRun) {
            $items = $selectedRun->items()->with(['product', 'correctionRequest'])->orderByRaw("CASE status WHEN 'MISMATCH' THEN 0 WHEN 'UNMAPPED' THEN 1 ELSE 2 END")->paginate(30);
        }
        return view('marketplaces.inventory-reconciliation.index', compact('connections', 'runs', 'selectedRun', 'items'));
    }

    public function run(Request $request, MarketplaceConnection $connection, MarketplaceInventoryReconciliationService $service)
    {
        abort_unless($connection->business_id === $request->user()->business_id, 404);
        $run = $service->reconcile($connection);
        return redirect()->route('marketplaces.inventory-reconciliation', ['run' => $run->id])
            ->with('success', 'Inventory reconciliation completed. No stock quantities were changed.');
    }

    public function requestCorrection(Request $request, MarketplaceInventoryReconciliationItem $item, MarketplaceInventoryReconciliationService $service)
    {
        $item->loadMissing('run');
        abort_unless($item->run?->business_id === $request->user()->business_id, 404);
        $service->requestCorrection($item, $request->user());
        return back()->with('success', 'A pending stock correction request was created. Stock remains unchanged until an authorized reviewer approves it.');
    }
}
