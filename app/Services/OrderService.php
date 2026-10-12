<?php
namespace App\Services;

use App\Models\{Marketplace, Order, OrderStatusHistory, Product, User};
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private InventoryService $inventory, private ProductCostService $costs) {}

    /**
     * Create an order + lines. Stock is deducted through the ledger, and the cost in force ON THE ORDER DATE
     * is snapshotted per line. Same (marketplace, external_order_id) can never be created twice.
     * Order lines are never edited afterwards (cancel + recreate) so financial history stays intact.
     */
    public function create(array $data, array $items, User $user): Order
    {
        $marketplace = Marketplace::findOrFail($data['marketplace_id']);
        $ext = trim($data['external_order_id']);
        $dup = ValidationException::withMessages(['external_order_id' => "Order {$ext} already exists for {$marketplace->name}."]);

        if (Order::withTrashed()->where('marketplace_id', $marketplace->id)->where('external_order_id', $ext)->exists()) throw $dup;

        try {
            return DB::transaction(fn () => $this->doCreate($marketplace, $ext, $data, $items, $user));
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') throw $dup; // unique-key backstop (race)
            throw $e;
        }
    }

    private function doCreate(Marketplace $mp, string $ext, array $data, array $items, User $user): Order
    {
        $date = Carbon::parse($data['order_date']);
        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');
        $sub = 0; $tax = 0; $lines = [];

        foreach ($items as $it) {
            $p = $products[$it['product_id']];
            $qty = (int) $it['quantity'];
            // Importers may send the whole line value ('line_total') so the total stays exact (no per-unit rounding drift).
            $gross = isset($it['line_total']) ? Money::p($it['line_total']) : $qty * Money::p($it['unit_price']);
            $unitP = isset($it['line_total']) ? (int) round($gross / $qty) : Money::p($it['unit_price']);
            $disc = Money::p($it['discount'] ?? 0);
            if ($disc > $gross) throw ValidationException::withMessages(['items' => "Discount is more than the line value for {$p->sku}."]);
            $line = $gross - $disc;
            $rate = (float) $p->gst_rate;
            $lineTax = (int) round($line * $rate / (100 + $rate));
            $other = Money::p($this->costs->costAt($p, 'LABEL', $date)) + Money::p($this->costs->costAt($p, 'TRANSPORT', $date)) + Money::p($this->costs->costAt($p, 'OTHER', $date));
            $sub += $line; $tax += $lineTax;
            $lines[] = ['product' => $p, 'row' => [
                'product_id' => $p->id, 'external_line_id' => $it['external_line_id'] ?? null, 'marketplace_sku' => $it['marketplace_sku'] ?? null,
                'quantity' => $qty, 'unit_price' => Money::m($unitP), 'discount' => Money::m($disc),
                'gst_rate' => number_format($rate, 2, '.', ''), 'tax_amount' => Money::m($lineTax), 'line_total' => Money::m($line),
                'cogs_at_sale' => $this->costs->costAt($p, 'COGS', $date), 'packaging_at_sale' => $this->costs->costAt($p, 'PACKAGING', $date),
                'other_cost_at_sale' => Money::m($other),
            ]];
        }
        $ship = Money::p($data['shipping_charged'] ?? 0);
        $status = $data['status'] ?? 'CONFIRMED';
        // Cancelled orders never touch stock. Historical imports can switch deduction off (deduct_stock=false).
        $deduct = ($data['deduct_stock'] ?? true) && $status !== 'CANCELLED';

        $order = Order::create([
            'marketplace_id' => $mp->id, 'external_order_id' => $ext, 'order_number' => 'TMP' . bin2hex(random_bytes(8)),
            'order_date' => $date, 'status' => $status, 'cancelled_at' => $status === 'CANCELLED' ? now() : null, 'marketplace_status' => $data['marketplace_status'] ?? null,
            'fulfillment_type' => $data['fulfillment_type'] ?? 'SELF', 'customer_ref' => $data['customer_ref'] ?? null, 'ship_state' => $data['ship_state'] ?? null,
            'subtotal' => Money::m($sub), 'shipping_charged' => Money::m($ship), 'tax_amount' => Money::m($tax), 'total_amount' => Money::m($sub + $ship),
            'source' => $data['source'] ?? 'MANUAL', 'import_id' => $data['import_id'] ?? null, 'notes' => $data['notes'] ?? null, 'created_by' => $user->id,
        ]);
        $order->update(['order_number' => sprintf('SG-%s-%06d', now()->format('Y'), $order->id)]);

        foreach ($lines as $l) {
            $item = $order->items()->create($l['row']);
            if ($deduct) {
                $this->inventory->record($l['product'], 'ORDER', $item->quantity, [
                    'reference' => $order, 'note' => "Order {$ext}", 'allow_negative' => true, // marketplace already sold it
                ]);
                $item->update(['qty_deducted' => $item->quantity]);
            }
        }
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => null, 'to_status' => $order->status, 'note' => ($data['source'] ?? 'MANUAL') === 'CSV' ? 'Imported from CSV' . ($deduct ? '' : ' (stock not deducted)') : 'Order created', 'user_id' => $user->id]);
        app(ProfitService::class)->safe($order);   // estimated profit is stored the moment the order exists
        ActivityLogger::log('order.created', $order, null, ['order' => $order->order_number, 'external' => $ext, 'total' => (string) $order->total_amount]);
        return $order;
    }

    public function changeStatus(Order $order, string $to, ?User $user = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $user, $note) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($to, Order::TRANSITIONS[$o->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot move order from {$o->status} to {$to}."]);
            }
            $upd = ['status' => $to];
            if ($to === 'SHIPPED') $upd['shipped_at'] = $o->shipped_at ?? now();
            if ($to === 'DELIVERED') $upd['delivered_at'] = $o->delivered_at ?? now();
            if ($to === 'CANCELLED') {
                $upd['cancelled_at'] = now();
                foreach ($o->items()->with('product')->get() as $item) {      // give the stock back
                    if ($item->qty_deducted > 0) {
                        $this->inventory->record($item->product, 'ORDER_CANCEL', (int) $item->qty_deducted, ['reference' => $o, 'note' => "Cancelled {$o->external_order_id}"]);
                        $item->update(['qty_deducted' => 0]);
                    }
                }
            }
            $from = $o->status;
            $o->update($upd);
            OrderStatusHistory::create(['order_id' => $o->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'user_id' => $user?->id ?? auth()->id()]);
            app(ProfitService::class)->safe($o);
            ActivityLogger::log('order.status_changed', $o, ['status' => $from], ['status' => $to]);
            return $o;
        });
    }
}
