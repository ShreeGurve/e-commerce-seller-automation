<?php

namespace App\Http\Controllers;

use App\Models\{Order, OrderFulfillment, OrderFulfillmentEvent};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class FulfilmentController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        $orders = Order::with(['marketplace', 'fulfillment'])
            ->whereIn('status', ['CONFIRMED', 'PACKED', 'SHIPPED'])
            ->when($request->filled('marketplace_id'), fn ($q) => $q->where('marketplace_id', $request->integer('marketplace_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('order_number', 'like', '%' . $request->q . '%')->orWhere('external_order_id', 'like', '%' . $request->q . '%')))
            ->latest('order_date')->paginate(30)->withQueryString();

        return view('fulfilment.index', compact('orders'));
    }

    public function update(Request $request, Order $order)
    {
        if (! in_array($order->status, ['CONFIRMED', 'PACKED'], true)) {
            throw ValidationException::withMessages(['fulfilment' => 'Packing details can only be edited before dispatch.']);
        }
        $data = $request->validate([
            'package_reference' => 'nullable|string|max:80',
            'weight_kg' => 'nullable|numeric|min:0.001|max:99999',
            'length_cm' => 'nullable|numeric|min:0.1|max:99999',
            'width_cm' => 'nullable|numeric|min:0.1|max:99999',
            'height_cm' => 'nullable|numeric|min:0.1|max:99999',
            'packing_notes' => 'nullable|string|max:2000',
            'carrier' => 'nullable|string|max:100',
            'service_level' => 'nullable|string|max:80',
            'tracking_number' => 'nullable|string|max:120',
            'tracking_url' => 'nullable|url|max:500',
            'invoice_number' => 'nullable|string|max:100',
            'pickup_reference' => 'nullable|string|max:120',
            'pickup_scheduled_at' => 'nullable|date',
            'label' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $fulfilment = $order->fulfillment()->firstOrNew();
        foreach (['package_reference','weight_kg','length_cm','width_cm','height_cm','packing_notes','carrier','service_level','tracking_number','tracking_url','invoice_number','pickup_reference','pickup_scheduled_at'] as $field) {
            if (array_key_exists($field, $data)) $fulfilment->{$field} = $data[$field] ?: null;
        }
        foreach (['label' => 'label_path', 'invoice' => 'invoice_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                if ($fulfilment->{$column}) Storage::disk('local')->delete($fulfilment->{$column});
                $fulfilment->{$column} = $request->file($input)->store('private/fulfilment/' . $order->id, 'local');
            }
        }
        $fulfilment->order_id = $order->id;
        $fulfilment->save();
        $this->event($order, 'DETAILS_UPDATED', 'Packing and dispatch details updated.', $request->user()->id);
        return back()->with('success', 'Fulfilment details saved.');
    }

    public function pack(Request $request, Order $order)
    {
        if ($order->status !== 'CONFIRMED') throw ValidationException::withMessages(['status' => 'Only confirmed orders can be marked packed.']);
        $f = $order->fulfillment;
        if (! $f || ! $f->weight_kg || ! $f->length_cm || ! $f->width_cm || ! $f->height_cm) {
            throw ValidationException::withMessages(['package' => 'Save package weight and all three dimensions before marking this order packed.']);
        }
        $this->orders->changeStatus($order, 'PACKED', $request->user(), 'Packed and ready for dispatch.');
        $f->update(['packed_by' => $request->user()->id, 'packed_at' => now()]);
        $this->event($order, 'PACKED', 'Order packed and ready for dispatch.', $request->user()->id);
        return back()->with('success', 'Order marked as packed.');
    }

    public function dispatch(Request $request, Order $order)
    {
        if ($order->status !== 'PACKED') throw ValidationException::withMessages(['status' => 'Only packed orders can be dispatched.']);
        $data = $request->validate([
            'carrier' => 'required|string|max:100',
            'tracking_number' => 'required|string|max:120',
            'tracking_url' => 'nullable|url|max:500',
        ]);
        $f = $order->fulfillment()->firstOrNew();
        $f->fill($data);
        $f->order_id = $order->id;
        $f->dispatched_by = $request->user()->id;
        $f->save();
        $this->orders->changeStatus($order, 'SHIPPED', $request->user(), 'Dispatched via ' . $data['carrier'] . ', tracking ' . $data['tracking_number']);
        $this->event($order, 'DISPATCHED', 'Shipment handed over to carrier.', $request->user()->id, ['carrier' => $data['carrier'], 'tracking_number' => $data['tracking_number']]);
        return back()->with('success', 'Order dispatched and tracking details saved.');
    }

    public function pickup(Request $request, Order $order)
    {
        if (! in_array($order->status, ['PACKED', 'SHIPPED'], true)) throw ValidationException::withMessages(['status' => 'Pickup can only be recorded for packed or shipped orders.']);
        $data = $request->validate(['picked_up_at' => 'required|date', 'pickup_reference' => 'nullable|string|max:120']);
        $f = $order->fulfillment()->firstOrNew();
        $f->fill($data);
        $f->order_id = $order->id;
        $f->save();
        $this->event($order, 'PICKED_UP', 'Carrier pickup recorded.', $request->user()->id, $data);
        return back()->with('success', 'Carrier pickup recorded.');
    }

    public function file(Request $request, Order $order, string $type)
    {
        abort_unless(in_array($type, ['label', 'invoice'], true), 404);
        $f = $order->fulfillment;
        abort_unless($f, 404);
        $path = $type === 'label' ? $f->label_path : $f->invoice_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }

    private function event(Order $order, string $type, string $description, ?int $userId, array $meta = []): void
    {
        OrderFulfillmentEvent::create(['order_id' => $order->id, 'event_type' => $type, 'description' => $description, 'meta' => $meta ?: null, 'user_id' => $userId, 'created_at' => now()]);
    }
}
