<?php
namespace App\Services;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    // Money is summed as integer paise (no float drift), stored as DECIMAL(15,2).
    private function p(string|int|float|null $v): int { return (int) round(((float) $v) * 100); }
    private function m(int $paise): string { return number_format($paise / 100, 2, '.', ''); }

    /** Create (null) or update (editable) a purchase with its lines. */
    public function save(?Purchase $purchase, array $data, array $items, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $items, $user) {
            $sub = 0; $gst = 0; $lines = [];
            foreach ($items as $it) {
                $qty = (int) $it['quantity'];
                $unit = $this->p($it['unit_cost']);
                $rate = (float) ($it['gst_rate'] ?? 0);
                $lineSub = $qty * $unit;
                $lineGst = (int) round($lineSub * $rate / 100);
                $sub += $lineSub; $gst += $lineGst;
                $lines[] = ['product_id' => $it['product_id'], 'quantity' => $qty, 'unit_cost' => $this->m($unit), 'gst_rate' => number_format($rate, 2, '.', ''),
                    'line_subtotal' => $this->m($lineSub), 'gst_amount' => $this->m($lineGst), 'line_total' => $this->m($lineSub + $lineGst)];
            }
            $ship = $this->p($data['shipping_charges'] ?? 0);
            $other = $this->p($data['other_charges'] ?? 0);
            $total = $sub + $gst + $ship + $other;

            $fields = [
                'supplier_id' => $data['supplier_id'], 'purchase_date' => $data['purchase_date'],
                'invoice_number' => $data['invoice_number'] ?? null, 'invoice_date' => $data['invoice_date'] ?? null,
                'subtotal' => $this->m($sub), 'gst_amount' => $this->m($gst),
                'shipping_charges' => $this->m($ship), 'other_charges' => $this->m($other),
                'total_amount' => $this->m($total), 'notes' => $data['notes'] ?? null,
            ];

            if (! $purchase) {
                $purchase = Purchase::create($fields + [
                    'po_number' => 'TMP' . Str::random(12), 'status' => $data['status'] ?? 'DRAFT',
                    'payment_status' => 'UNPAID', 'created_by' => $user->id,
                ]);
                $purchase->update(['po_number' => sprintf('PO-%s-%05d', now()->format('Y'), $purchase->id)]);
                ActivityLogger::log('purchase.created', $purchase, null, ['po' => $purchase->po_number, 'total' => $this->m($total)]);
            } else {
                $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                $this->assertEditable($purchase);
                $old = ['total' => (string) $purchase->total_amount, 'status' => $purchase->status];
                $purchase->update($fields + ['status' => $data['status'] ?? $purchase->status]);
                $purchase->items()->delete();
                ActivityLogger::log('purchase.updated', $purchase, $old, ['total' => $this->m($total)]);
            }

            foreach ($lines as $l) {
                $purchase->items()->create($l);
            }
            $this->syncPaymentStatus($purchase);
            return $purchase;
        });
    }

    /** Receive ALL items -> one PURCHASE ledger row per product. Safe to click twice: second call is rejected. */
    public function receive(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($purchase);

            foreach ($purchase->items()->with('product')->get() as $item) {
                $this->inventory->record($item->product, 'PURCHASE', (int) $item->quantity, [
                    'unit_cost' => $item->unit_cost, 'reference' => $purchase, 'note' => 'PO ' . $purchase->po_number,
                ]);
                $item->update(['received_qty' => $item->quantity]);
            }
            $old = $purchase->status;
            $purchase->update(['status' => 'RECEIVED', 'received_at' => now()]);
            ActivityLogger::log('purchase.received', $purchase, ['status' => $old], ['status' => 'RECEIVED']);
            return $purchase;
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($purchase); // received stock needs a return-to-supplier flow (later phase)
            $old = $purchase->status;
            $purchase->update(['status' => 'CANCELLED']);
            ActivityLogger::log('purchase.cancelled', $purchase, ['status' => $old], ['status' => 'CANCELLED']);
            return $purchase;
        });
    }

    public function addPayment(Purchase $purchase, string|float $amount): Purchase
    {
        return DB::transaction(function () use ($purchase, $amount) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->status === 'CANCELLED') {
                throw ValidationException::withMessages(['amount' => 'Cancelled purchase cannot take payments.']);
            }
            $paid = $this->p($purchase->amount_paid) + $this->p($amount);
            if ($paid > $this->p($purchase->total_amount)) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds balance due (' . $purchase->balance() . ').']);
            }
            $old = (string) $purchase->amount_paid;
            $purchase->update(['amount_paid' => $this->m($paid)]);
            $this->syncPaymentStatus($purchase);
            ActivityLogger::log('purchase.payment', $purchase, ['paid' => $old], ['paid' => $this->m($paid)]);
            return $purchase;
        });
    }

    private function syncPaymentStatus(Purchase $purchase): void
    {
        $paid = $this->p($purchase->amount_paid); $total = $this->p($purchase->total_amount);
        $status = $paid <= 0 ? 'UNPAID' : ($paid >= $total ? 'PAID' : 'PARTIAL');
        if ($purchase->payment_status !== $status) {
            $purchase->update(['payment_status' => $status]);
        }
    }

    private function assertEditable(Purchase $purchase): void
    {
        if (! $purchase->isEditable()) {
            throw ValidationException::withMessages(['status' => "Purchase is already {$purchase->status}; this action is not allowed."]);
        }
    }
}
