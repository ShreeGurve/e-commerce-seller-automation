<?php
namespace App\Services;

use App\Models\{Order, ProductReturn, Refund, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private OrderService $orders) {}

    /** Total refunds can never exceed the order total. A full refund moves a RETURNED order to REFUNDED. */
    public function record(Order $order, array $data, User $user): Refund
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['IMPORTED', 'CONFIRMED', 'PACKED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages(['amount' => 'Refunds are only possible for shipped orders.']);
            }
            $amount = Money::p($data['amount']);
            $already = Money::p(Refund::where('order_id', $order->id)->sum('amount'));
            $total = Money::p($order->total_amount);
            if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Amount must be more than zero.']);
            if ($already + $amount > $total) {
                throw ValidationException::withMessages(['amount' => 'Refund exceeds order total. Still refundable: ₹' . number_format(($total - $already) / 100, 2)]);
            }
            $ext = $data['external_refund_id'] ?? null;
            if ($ext && Refund::withTrashed()->where('order_id', $order->id)->where('external_refund_id', $ext)->exists()) {
                throw ValidationException::withMessages(['external_refund_id' => 'This refund id was already recorded.']);
            }
            $returnId = $data['return_id'] ?? null;
            if ($returnId && ! ProductReturn::where('id', $returnId)->where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['return_id' => 'Return does not belong to this order.']);
            }
            $refund = Refund::create(['order_id' => $order->id, 'return_id' => $returnId ?: null, 'external_refund_id' => $ext,
                'amount' => Money::m($amount), 'refund_date' => $data['refund_date'], 'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by' => $user->id]);
            app(ProfitService::class)->safe($order);
            ActivityLogger::log('refund.recorded', $order, null, ['amount' => Money::m($amount), 'order' => $order->order_number]);

            if ($already + $amount === $total && $order->status === 'RETURNED') {
                $this->orders->changeStatus($order, 'REFUNDED', $user, 'Fully refunded');
            }
            return $refund;
        });
    }
}
