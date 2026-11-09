<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use Illuminate\Http\Request;

class OrderOperationsController extends Controller
{
    public function sla(Request $request)
    {
        $days = (int) $request->input('age_days', 1);
        if (!in_array($days, [1, 2, 3, 5, 7], true)) $days = 1;

        $orders = Order::query()->with(['marketplace', 'fulfillment'])
            ->whereIn('status', ['IMPORTED', 'CONFIRMED', 'PACKED'])
            ->where('fulfillment_type', 'SELF')
            ->where('order_date', '<=', now()->subDays($days))
            ->orderBy('order_date')
            ->paginate(30)->withQueryString();

        $summary = [
            'overdue' => Order::whereIn('status', ['IMPORTED', 'CONFIRMED', 'PACKED'])->where('fulfillment_type', 'SELF')->where('order_date', '<=', now()->subDays($days))->count(),
            'imported' => Order::where('status', 'IMPORTED')->where('fulfillment_type', 'SELF')->where('order_date', '<=', now()->subDays($days))->count(),
            'confirmed' => Order::where('status', 'CONFIRMED')->where('fulfillment_type', 'SELF')->where('order_date', '<=', now()->subDays($days))->count(),
            'packed' => Order::where('status', 'PACKED')->where('fulfillment_type', 'SELF')->where('order_date', '<=', now()->subDays($days))->count(),
        ];

        return view('orders.operations-sla', compact('orders', 'summary', 'days'));
    }

    public function fulfillmentExceptions()
    {
        $orders = Order::query()->with(['marketplace', 'fulfillment'])
            ->where('fulfillment_type', 'SELF')
            ->whereIn('status', ['PACKED', 'SHIPPED'])
            ->get()
            ->filter(function ($order) {
                $f = $order->fulfillment;
                if (!$f) return true;
                if ($order->status === 'PACKED') return !$f->carrier || !$f->tracking_number || !$f->invoice_number || !$f->packed_at;
                if ($order->status === 'SHIPPED') return !$f->tracking_number || !$order->shipped_at;
                return false;
            })->values();

        $summary = [
            'exceptions' => $orders->count(),
            'packed' => $orders->filter(fn ($o) => $o->status === 'PACKED')->count(),
            'shipped' => $orders->filter(fn ($o) => $o->status === 'SHIPPED')->count(),
        ];
        return view('orders.fulfillment-exceptions', compact('orders', 'summary'));
    }

    public function returnIntake()
    {
        $returns = ProductReturn::query()->with(['order', 'marketplace', 'items.product'])
            ->whereIn('status', ['REQUESTED', 'INSPECTED'])
            ->latest('return_date')->paginate(30);

        $summary = [
            'open_returns' => ProductReturn::whereIn('status', ['REQUESTED', 'INSPECTED'])->count(),
            'expected_units' => (int) ReturnItem::whereHas('productReturn', fn ($q) => $q->whereIn('status', ['REQUESTED', 'INSPECTED']))->sum('quantity'),
            'received_units' => (int) ReturnItem::whereHas('productReturn', fn ($q) => $q->whereIn('status', ['REQUESTED', 'INSPECTED']))->sum('received_qty'),
            'unreceived_units' => (int) ReturnItem::whereHas('productReturn', fn ($q) => $q->whereIn('status', ['REQUESTED', 'INSPECTED']))->selectRaw('COALESCE(SUM(GREATEST(quantity - received_qty, 0)), 0) as aggregate')->value('aggregate'),
        ];
        return view('returns.intake-monitor', compact('returns', 'summary'));
    }
}
