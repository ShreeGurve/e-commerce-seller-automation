<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ShipmentTrackingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentTrackingController extends Controller
{
    public function store(Request $request, Order $order, ShipmentTrackingService $tracking)
    {
        abort_unless((int) $order->created_by === (int) $request->user()->id || $request->user()->can('orders.edit'), 403);

        $data = $request->validate([
            'tracking_status' => ['required', 'string', Rule::in(array_keys(ShipmentTrackingService::STATUSES))],
            'description' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $tracking->record(
            $order,
            $data['tracking_status'],
            $request->user(),
            $data['description'] ?? null,
            $data['location'] ?? null,
            $data['occurred_at'] ?? null,
        );

        return back()->with('success', 'Shipment tracking event recorded.');
    }
}
