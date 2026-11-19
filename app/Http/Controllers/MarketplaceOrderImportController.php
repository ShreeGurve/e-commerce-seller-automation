<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceOrderImport;
use App\Models\MarketplaceSyncRun;
use App\Services\MarketplaceOrderImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketplaceOrderImportController extends Controller
{
    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $imports = MarketplaceOrderImport::query()
            ->with(['connection.marketplace', 'order'])
            ->where('business_id', $businessId)
            ->latest()->paginate(25);

        return view('marketplaces.order-imports.index', compact('imports'));
    }

    public function prepare(Request $request, MarketplaceSyncRun $run, MarketplaceOrderImportService $service)
    {
        abort_unless((int) $run->business_id === (int) $request->user()->business_id, 404);
        abort_unless($run->resource === 'orders' && in_array($run->status, ['COMPLETED', 'PARTIAL'], true), 422);

        $count = $service->previewFromSyncRun($run->id, (int) $request->user()->business_id);
        return redirect()->route('marketplaces.order-imports.index')
            ->with('success', $count.' new order review candidate(s) prepared.');
    }

    public function approve(Request $request, MarketplaceOrderImport $import, MarketplaceOrderImportService $service)
    {
        abort_unless((int) $import->business_id === (int) $request->user()->business_id, 404);
        $validated = $request->validate(['deduct_stock' => ['nullable', 'boolean']]);
        $service->approveAndImport($import, $request->user(), (bool) ($validated['deduct_stock'] ?? false));
        return back()->with('success', 'Marketplace order approved and imported.');
    }

    public function reject(Request $request, MarketplaceOrderImport $import, MarketplaceOrderImportService $service)
    {
        abort_unless((int) $import->business_id === (int) $request->user()->business_id, 404);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $service->reject($import, $request->user(), $validated['reason'] ?? null);
        return back()->with('success', 'Marketplace order import rejected.');
    }
}
