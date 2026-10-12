<?php
namespace App\Http\Controllers;

use App\Models\{Marketplace, Settlement, SettlementItem};
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $settlements = Settlement::with('marketplace')->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->latest('period_end')->latest('id')->paginate(25)->withQueryString();
        return view('settlements.index', ['settlements' => $settlements, 'marketplaces' => Marketplace::active()->get(),
            'unmatched' => SettlementItem::whereNull('order_id')->whereNotNull('external_order_id')->count()]);
    }

    public function show(Request $request, Settlement $settlement)
    {
        $settlement->load('marketplace');
        $byKind = $settlement->items()->selectRaw('kind, COUNT(*) n, SUM(amount) amt')->groupBy('kind')->orderBy('kind')->get();
        $items = $settlement->items()->with('order:id,order_number,external_order_id')
            ->when($request->kind, fn ($q, $v) => $q->where('kind', $v))->when($request->get('unmatched'), fn ($q) => $q->whereNull('order_id')->whereNotNull('external_order_id'))
            ->orderBy('id')->paginate(50)->withQueryString();
        return view('settlements.show', compact('settlement', 'byKind', 'items'));
    }
}
