<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Models\MarketplaceRefundImport;
use App\Models\MarketplaceReturnImport;
use App\Models\ProductReturn;
use App\Services\MarketplaceReturnsService;
use Illuminate\Http\Request;

class MarketplaceReturnsController extends Controller
{
    public function index()
    {
        return view('marketplaces.returns.index', [
            'returnImports' => MarketplaceReturnImport::with('marketplace')->latest()->limit(50)->get(),
            'refundImports' => MarketplaceRefundImport::with('marketplace')->latest()->limit(50)->get(),
            'returns' => ProductReturn::with(['order', 'marketplace', 'items'])->latest()->limit(50)->get(),
            'marketplaces' => Marketplace::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function stageReturn(Request $request, MarketplaceReturnsService $service)
    {
        $data = $request->validate(['marketplace_id' => 'required|exists:marketplaces,id', 'external_return_id' => 'required|string|max:80',
            'external_order_id' => 'required|string|max:60', 'return_date' => 'required|date', 'return_type' => 'nullable|string|max:30',
            'reason_text' => 'nullable|string|max:500', 'items' => 'required|array|min:1', 'items.*.external_line_id' => 'nullable|string|max:60',
            'items.*.marketplace_sku' => 'nullable|string|max:120', 'items.*.quantity' => 'required|integer|min:1']);
        $service->stageReturn(Marketplace::findOrFail($data['marketplace_id']), $data);

        return back()->with('success', 'Return staged for review.');
    }

    public function approveReturn(MarketplaceReturnImport $import, MarketplaceReturnsService $service)
    {
        $service->approveReturn($import, auth()->user());

        return back()->with('success', 'Marketplace return imported into the return register.');
    }

    public function rejectReturn(Request $request, MarketplaceReturnImport $import, MarketplaceReturnsService $service)
    {
        $data = $request->validate(['note' => 'required|string|max:1000']);
        $service->rejectReturn($import, auth()->user(), $data['note']);

        return back()->with('success', 'Return import rejected.');
    }

    public function inspect(Request $request, ProductReturn $return, MarketplaceReturnsService $service)
    {
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*.return_item_id' => 'required|integer',
            'items.*.received_qty' => 'required|integer|min:0', 'items.*.condition' => 'required|in:SELLABLE,USED,DAMAGED,SCRAP,MISSING',
            'items.*.restock_qty' => 'nullable|integer|min:0', 'items.*.note' => 'nullable|string|max:500']);
        $service->inspect($return, $data['items'], auth()->user());

        return back()->with('success', 'Return inspection saved. Inventory quantities were not changed.');
    }

    public function stageRefund(Request $request, MarketplaceReturnsService $service)
    {
        $data = $request->validate(['marketplace_id' => 'required|exists:marketplaces,id', 'external_refund_id' => 'required|string|max:80',
            'external_order_id' => 'required|string|max:60', 'external_return_id' => 'nullable|string|max:80', 'amount' => 'required|numeric|gt:0',
            'currency' => 'nullable|string|size:3', 'refund_date' => 'required|date', 'reason' => 'nullable|string|max:180']);
        $service->stageRefund(Marketplace::findOrFail($data['marketplace_id']), $data);

        return back()->with('success', 'Refund staged for reconciliation.');
    }

    public function reconcileRefund(MarketplaceRefundImport $import, MarketplaceReturnsService $service)
    {
        $service->reconcileRefund($import, auth()->user());

        return back()->with('success', 'Marketplace refund reconciled with the existing refund ledger.');
    }
}
