<?php
namespace App\Services;

use App\Models\{Order, OrderItem, ProductReturn, ReturnItem, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(private InventoryService $inventory, private OrderService $orders) {}

    /** @param array<int,int> $lines order_item_id => quantity */
    public function create(Order $order, array $data, array $lines, User $user): ProductReturn
    {
        return DB::transaction(function () use ($order, $data, $lines, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['SHIPPED', 'DELIVERED', 'RETURNED'], true)) {
                throw ValidationException::withMessages(['order' => 'Returns are only possible after the order is shipped. Cancel the order instead.']);
            }
            $ext = $data['external_return_id'] ?? null;
            if ($ext && ProductReturn::withTrashed()->where('marketplace_id', $order->marketplace_id)->where('external_return_id', $ext)->exists()) {
                throw ValidationException::withMessages(['external_return_id' => 'This marketplace return id already exists.']);
            }
            $lines = array_filter(array_map('intval', $lines), fn ($q) => $q > 0);
            if (! $lines) throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one item.']);

            $items = OrderItem::where('order_id', $order->id)->whereIn('id', array_keys($lines))->get()->keyBy('id');
            $return = ProductReturn::create([
                'order_id' => $order->id, 'marketplace_id' => $order->marketplace_id, 'return_number' => 'TMP' . bin2hex(random_bytes(8)),
                'external_return_id' => $ext, 'return_date' => $data['return_date'], 'return_type' => $data['return_type'],
                'reason_text' => $data['reason_text'] ?? null, 'status' => 'REQUESTED', 'created_by' => $user->id,
            ]);
            $return->update(['return_number' => sprintf('RT-%s-%06d', now()->format('Y'), $return->id)]);

            foreach ($lines as $itemId => $qty) {
                $item = $items[$itemId] ?? throw ValidationException::withMessages(['items' => 'Item does not belong to this order.']);
                $already = (int) ReturnItem::where('order_item_id', $item->id)->sum('quantity');
                if ($qty > $item->quantity - $already) {
                    throw ValidationException::withMessages(['items' => "Cannot return {$qty} of {$item->product->sku}: only " . ($item->quantity - $already) . ' left to return.']);
                }
                $return->items()->create(['order_item_id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $qty]);
            }
            if (in_array($order->status, ['SHIPPED', 'DELIVERED'], true)) {
                $this->orders->changeStatus($order, 'RETURNED', $user, "Return {$return->return_number}");
            }
            ActivityLogger::log('return.created', $return, null, ['return' => $return->return_number, 'order' => $order->order_number]);
            return $return;
        });
    }

    /**
     * Inspection: expected vs received. Sellable units go back to available stock; used/damaged/scrap go to the
     * damaged bucket; nothing received = Missing. Runs once per return (second call is rejected).
     * @param array<int,array{received_qty:int,condition:?string,note:?string}> $lines keyed by return_item id
     */
    public function inspect(ProductReturn $return, array $lines, bool $claimEligible, ?string $notes, User $user): ProductReturn
    {
        return DB::transaction(function () use ($return, $lines, $claimEligible, $notes, $user) {
            $return = ProductReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->status !== 'REQUESTED') throw ValidationException::withMessages(['status' => 'This return was already inspected.']);

            foreach ($return->items()->with('product')->get() as $item) {
                $l = $lines[$item->id] ?? throw ValidationException::withMessages(['items' => 'Inspection data missing for a line.']);
                $recv = (int) ($l['received_qty'] ?? 0);
                if ($recv < 0 || $recv > $item->quantity) {
                    throw ValidationException::withMessages(['items' => "Received qty for {$item->product->sku} must be 0 to {$item->quantity}."]);
                }
                $cond = $recv === 0 ? 'MISSING' : ($l['condition'] ?? null);
                if (! in_array($cond, array_keys(ReturnItem::CONDITIONS), true) || ($recv > 0 && $cond === 'MISSING')) {
                    throw ValidationException::withMessages(['items' => "Choose a condition for {$item->product->sku}."]);
                }
                $restock = 0;
                if ($recv > 0) {
                    if ($cond === 'SELLABLE') { $this->inventory->record($item->product, 'RETURN', $recv, ['reference' => $return, 'note' => $return->return_number]); $restock = $recv; }
                    else { $this->inventory->record($item->product, 'RETURN_DAMAGED', $recv, ['reference' => $return, 'note' => "{$return->return_number} ({$cond})"]); }
                }
                $item->update(['received_qty' => $recv, 'condition' => $cond, 'restocked_qty' => $restock, 'note' => $l['note'] ?? null]);
            }
            $return->update(['status' => 'INSPECTED', 'inspected_at' => now(), 'claim_eligible' => $claimEligible,
                'notes' => $notes ?: $return->notes]);
            app(ProfitService::class)->safe($return->order);   // restocked units give their cost back
            ActivityLogger::log('return.inspected', $return, ['status' => 'REQUESTED'], ['status' => 'INSPECTED', 'claim_eligible' => $claimEligible]);
            return $return;
        });
    }

    public function close(ProductReturn $return): ProductReturn
    {
        return DB::transaction(function () use ($return) {
            $return = ProductReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->status !== 'INSPECTED') throw ValidationException::withMessages(['status' => 'Only inspected returns can be closed.']);
            $return->update(['status' => 'CLOSED']);
            ActivityLogger::log('return.closed', $return, ['status' => 'INSPECTED'], ['status' => 'CLOSED']);
            return $return;
        });
    }
}
