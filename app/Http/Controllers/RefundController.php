<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(Request $request, Order $order, RefundService $service)
    {
        $d = $request->validate([
            'amount' => 'required|numeric|gt:0|max:9999999999', 'refund_date' => 'required|date|before_or_equal:today',
            'reason' => 'nullable|string|max:255', 'external_refund_id' => 'nullable|string|max:60',
            'return_id' => 'nullable|integer', 'notes' => 'nullable|string|max:2000',
        ]);
        $service->record($order, $d, $request->user());
        return redirect()->route('orders.show', $order)->with('success', 'Refund recorded.');
    }
}
