<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierAnalyticsController extends Controller
{
    private function dateRange(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        return [$data['from'] ?? now()->startOfMonth()->toDateString(), $data['to'] ?? now()->toDateString()];
    }

    private function purchaseQuery(string $from, string $to)
    {
        return Purchase::with(['supplier', 'items.product'])
            ->whereBetween('purchase_date', [$from, $to])
            ->whereNotIn('status', ['DRAFT', 'CANCELLED']);
    }

    private function scoreRows($suppliers, $purchases)
    {
        $bySupplier = $purchases->groupBy('supplier_id');
        return $suppliers->map(function ($supplier) use ($bySupplier) {
            $orders = $bySupplier->get($supplier->id, collect());
            $evaluated = $orders->filter(fn ($p) => $p->status === 'RECEIVED' && $p->expected_delivery_date);
            $onTime = $evaluated->filter(fn ($p) => $p->received_at && $p->received_at->toDateString() <= $p->expected_delivery_date->toDateString())->count();
            $late = $evaluated->count() - $onTime;
            $overdue = $orders->filter(fn ($p) => $p->status === 'ORDERED' && $p->expected_delivery_date && $p->expected_delivery_date->toDateString() < now()->toDateString())->count();
            $receivedUnits = $orders->sum(fn ($p) => $p->items->sum('received_qty'));
            $orderedUnits = $orders->sum(fn ($p) => $p->items->sum('quantity'));
            $reviews = $supplier->reviews;
            $rating = $reviews->isEmpty() ? null : round($reviews->avg(fn ($r) => ($r->delivery_rating + $r->quality_rating + $r->pricing_rating) / 3), 1);
            return (object) [
                'supplier' => $supplier, 'orders' => $orders, 'order_count' => $orders->count(),
                'spend' => (float) $orders->sum('total_amount'), 'paid' => (float) $orders->sum('amount_paid'),
                'ordered_units' => $orderedUnits, 'received_units' => $receivedUnits,
                'on_time_count' => $onTime, 'late_count' => $late, 'overdue_count' => $overdue,
                'evaluated_count' => $evaluated->count(), 'on_time_rate' => $evaluated->count() ? round($onTime / $evaluated->count() * 100, 1) : null,
                'rating' => $rating, 'review_count' => $reviews->count(),
            ];
        });
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $purchases = $this->purchaseQuery($from, $to)->get();
        $suppliers = Supplier::with('reviews')->orderBy('name')->get();
        $scores = $this->scoreRows($suppliers, $purchases)->sortByDesc('spend')->values();
        $items = $purchases->flatMap(fn ($p) => $p->items->map(fn ($i) => (object) ['purchase' => $p, 'item' => $i]));
        $costChanges = $items->groupBy(fn ($r) => $r->item->product_id)->map(function ($rows) {
            $sorted = $rows->sortBy(fn ($r) => $r->purchase->purchase_date?->toDateString() . '-' . str_pad((string) $r->purchase->id, 12, '0', STR_PAD_LEFT))->values();
            if ($sorted->count() < 2) return null;
            $previous = $sorted[$sorted->count() - 2]; $latest = $sorted->last();
            $old = (float) $previous->item->unit_cost; $new = (float) $latest->item->unit_cost;
            return (object) ['product' => $latest->item->product, 'supplier' => $latest->purchase->supplier, 'old' => $old, 'new' => $new,
                'change' => $old > 0 ? round(($new - $old) / $old * 100, 1) : null, 'date' => $latest->purchase->purchase_date];
        })->filter()->sortByDesc(fn ($r) => abs($r->change ?? 0))->take(12)->values();

        return view('supplier-analytics.index', [
            'from' => $from, 'to' => $to, 'scores' => $scores, 'costChanges' => $costChanges,
            'totalSpend' => (float) $purchases->sum('total_amount'), 'totalOrders' => $purchases->count(),
            'totalSuppliers' => $scores->filter(fn ($r) => $r->order_count > 0)->count(),
            'overdueOrders' => $purchases->filter(fn ($p) => $p->status === 'ORDERED' && $p->expected_delivery_date && $p->expected_delivery_date->toDateString() < now()->toDateString())->count(),
            'onTimeEvaluated' => $purchases->filter(fn ($p) => $p->status === 'RECEIVED' && $p->expected_delivery_date)->count(),
            'onTimeCount' => $scores->sum('on_time_count'),
        ]);
    }

    public function show(Supplier $supplier, Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $purchases = $this->purchaseQuery($from, $to)->where('supplier_id', $supplier->id)->orderByDesc('purchase_date')->get();
        $supplier->load(['reviews.reviewer']);
        $evaluated = $purchases->filter(fn ($p) => $p->status === 'RECEIVED' && $p->expected_delivery_date);
        $onTime = $evaluated->filter(fn ($p) => $p->received_at && $p->received_at->toDateString() <= $p->expected_delivery_date->toDateString())->count();
        return view('supplier-analytics.show', [
            'supplier' => $supplier, 'purchases' => $purchases, 'from' => $from, 'to' => $to,
            'spend' => (float) $purchases->sum('total_amount'), 'onTimeRate' => $evaluated->count() ? round($onTime / $evaluated->count() * 100, 1) : null,
            'overdue' => $purchases->filter(fn ($p) => $p->status === 'ORDERED' && $p->expected_delivery_date && $p->expected_delivery_date->toDateString() < now()->toDateString())->count(),
        ]);
    }

    public function review(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'delivery_rating' => 'required|integer|between:1,5', 'quality_rating' => 'required|integer|between:1,5',
            'pricing_rating' => 'required|integer|between:1,5', 'notes' => 'nullable|string|max:2000',
        ]);
        $supplier->reviews()->create($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return redirect()->route('supplier-analytics.show', $supplier)->with('success', 'Supplier review saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $purchases = $this->purchaseQuery($from, $to)->get();
        $suppliers = Supplier::with('reviews')->orderBy('name')->get();
        $scores = $this->scoreRows($suppliers, $purchases);
        $filename = 'supplier-performance-' . $from . '-to-' . $to . '.csv';
        return response()->streamDownload(function () use ($scores) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Supplier', 'Purchase Orders', 'Purchase Value', 'Paid', 'Ordered Units', 'Received Units', 'On-time Deliveries', 'Late Deliveries', 'Overdue Orders', 'On-time Rate %', 'Internal Rating / 5', 'Reviews']);
            foreach ($scores as $r) fputcsv($out, [$r->supplier->name, $r->order_count, number_format($r->spend, 2, '.', ''), number_format($r->paid, 2, '.', ''), $r->ordered_units, $r->received_units, $r->on_time_count, $r->late_count, $r->overdue_count, $r->on_time_rate ?? 'N/A', $r->rating ?? 'N/A', $r->review_count]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
