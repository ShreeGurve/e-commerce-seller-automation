<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderOperationsInsightsController extends Controller
{
    /** Package 30: operational handover evidence; read-only. */
    public function carrierHandover(Request $request)
    {
        $from = $request->date('from') ?: now()->subDays(30)->toDateString();
        $to = $request->date('to') ?: now()->toDateString();
        $orders = Order::query()->where('fulfillment_type', 'SELF')
            ->whereIn('status', ['SHIPPED', 'DELIVERED'])
            ->whereBetween('shipped_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with(['marketplace', 'fulfillment', 'fulfillmentEvents'])
            ->orderByDesc('shipped_at')->paginate(30)->withQueryString();
        $orders->getCollection()->transform(function ($order) {
            $events = $order->fulfillmentEvents;
            $order->handover_event = $events->first(fn ($e) => in_array(strtoupper($e->event_type), ['PICKED_UP', 'PICKUP', 'HANDOVER', 'CARRIER_HANDOVER', 'DISPATCHED'], true));
            $order->handover_gap = ! $order->fulfillment?->picked_up_at && ! $order->handover_event;

            return $order;
        });

        return view('orders.carrier-handover', compact('orders', 'from', 'to'));
    }

    /** Package 31: elapsed delivery time and overdue shipments; read-only. */
    public function deliveryAging(Request $request)
    {
        $threshold = (int) $request->input('days', 5);
        if (! in_array($threshold, [2, 3, 5, 7, 10, 14], true)) {
            $threshold = 5;
        }
        $orders = Order::query()->where('fulfillment_type', 'SELF')
            ->whereNotNull('shipped_at')->whereIn('status', ['SHIPPED', 'DELIVERED'])
            ->with('marketplace')->orderBy('shipped_at')->paginate(30)->withQueryString();
        $orders->getCollection()->transform(function ($order) use ($threshold) {
            $end = $order->delivered_at ?: now();
            $order->delivery_age_days = max(0, (int) floor($order->shipped_at->diffInHours($end) / 24));
            $order->delivery_overdue = ! $order->delivered_at && $order->delivery_age_days >= $threshold;
            $order->delivery_duration_days = $order->delivered_at ? round($order->shipped_at->diffInHours($order->delivered_at) / 24, 1) : null;

            return $order;
        });

        return view('orders.delivery-aging', compact('orders', 'threshold'));
    }

    /** Package 32: condition versus restock quantity checks; read-only. */
    public function returnDisposition(Request $request)
    {
        $items = ReturnItem::query()->whereHas('productReturn')
            ->with(['productReturn.order.marketplace', 'product'])
            ->orderByDesc('id')->get();
        $exceptions = $items->filter(function ($item) {
            $received = (int) $item->received_qty;
            $restocked = (int) $item->restocked_qty;
            $condition = strtoupper((string) $item->condition);

            return ($condition === 'SELLABLE' && $restocked < $received)
                || (in_array($condition, ['USED', 'DAMAGED', 'SCRAP', 'MISSING'], true) && $restocked > 0)
                || $restocked > $received;
        });
        $conditionFilter = strtoupper((string) $request->input('condition', ''));
        if (in_array($conditionFilter, ['SELLABLE', 'USED', 'DAMAGED', 'SCRAP', 'MISSING'], true)) {
            $exceptions = $exceptions->filter(fn ($item) => strtoupper((string) $item->condition) === $conditionFilter);
        }
        $exceptions = $exceptions->values();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 30;
        $paginator = new LengthAwarePaginator(
            $exceptions->forPage($page, $perPage)->values(), $exceptions->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('returns.disposition-reconciliation', ['items' => $paginator, 'conditionFilter' => $conditionFilter]);
    }
}
