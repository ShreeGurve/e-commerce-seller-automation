<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Models\MarketplaceBankPayout;
use App\Models\Settlement;
use App\Services\MarketplacePayoutReconciliationService;
use Illuminate\Http\Request;

class MarketplacePayoutReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $payouts = MarketplaceBankPayout::with(['marketplace:id,name', 'settlement:id,external_settlement_id,total_amount'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('marketplace_id'), fn ($q, $id) => $q->where('marketplace_id', $id))
            ->latest('payout_date')->latest('id')->paginate(25)->withQueryString();

        $summary = [
            'total' => MarketplaceBankPayout::count(),
            'unmatched' => MarketplaceBankPayout::where('status', 'UNMATCHED')->count(),
            'mismatch' => MarketplaceBankPayout::where('status', 'MISMATCH')->count(),
            'matched' => MarketplaceBankPayout::where('status', 'MATCHED')->count(),
        ];
        return view('finance.payout-reconciliation.index', [
            'payouts' => $payouts,
            'marketplaces' => Marketplace::active()->orderBy('name')->get(),
            'settlements' => Settlement::with('marketplace')->whereDoesntHave('bankPayout')->latest('period_end')->limit(200)->get(),
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, MarketplacePayoutReconciliationService $service)
    {
        $data = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id',
            'settlement_id' => 'nullable|exists:settlements,id',
            'bank_reference' => 'required|string|max:100',
            'external_settlement_id' => 'nullable|string|max:60',
            'payout_date' => 'nullable|date',
            'bank_amount' => 'required|numeric|gt:0|max:999999999999.99',
            'currency' => 'required|in:INR',
            'note' => 'nullable|string|max:500',
        ]);
        $service->record($data, $request->user()->id);
        return back()->with('success', 'Bank payout recorded. Check its matching status below.');
    }

    public function link(Request $request, MarketplaceBankPayout $payout, MarketplacePayoutReconciliationService $service)
    {
        $data = $request->validate(['settlement_id' => 'required|exists:settlements,id']);
        $service->link($payout, Settlement::findOrFail($data['settlement_id']), $request->user()->id);
        return back()->with('success', 'Payout linked and amount comparison completed.');
    }

    public function ignore(Request $request, MarketplaceBankPayout $payout, MarketplacePayoutReconciliationService $service)
    {
        $data = $request->validate(['note' => 'required|string|max:500']);
        $service->ignore($payout, $data['note'], $request->user()->id);
        return back()->with('success', 'Payout marked as ignored with a review note.');
    }

    public function reopen(Request $request, MarketplaceBankPayout $payout, MarketplacePayoutReconciliationService $service)
    {
        $service->reopen($payout, $request->user()->id);
        return back()->with('success', 'Payout reopened for reconciliation.');
    }
}
