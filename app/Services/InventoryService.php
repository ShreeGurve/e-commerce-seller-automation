<?php
namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** The ONLY place that changes stock. Every change = 1 ledger row + 1 bucket update, in one DB transaction. */
class InventoryService
{
    /**
     * @param array{unit_cost?:string|float|null, note?:string|null, reference?:Model|null, allow_negative?:bool} $o
     */
    public function record(Product $product, string $type, int $quantity, array $o = []): InventoryTransaction
    {
        $def = InventoryTransaction::TYPES[$type] ?? throw new InvalidArgumentException("Unknown inventory type: $type");

        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity cannot be zero.']);
        }
        if ($type !== 'ADJUSTMENT' && $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be a positive number.']);
        }

        return DB::transaction(function () use ($product, $type, $quantity, $o, $def) {
            $this->ensureRow($product);
            $inv = Inventory::where('product_id', $product->id)->lockForUpdate()->first();

            $dAvail = $def['avail'] === 'signed' ? $quantity : $def['avail'] * $quantity;
            $dDamaged = $def['damaged'] * $quantity;
            $newAvail = $inv->available_qty + $dAvail;
            $newDamaged = $inv->damaged_qty + $dDamaged;

            if ($newAvail < 0 && ! ($o['allow_negative'] ?? false)) {
                throw ValidationException::withMessages(['quantity' => "Not enough stock. Available: {$inv->available_qty}."]);
            }
            if ($newDamaged < 0) {
                throw ValidationException::withMessages(['quantity' => "Not enough damaged stock. Damaged: {$inv->damaged_qty}."]);
            }

            $inv->forceFill(['available_qty' => $newAvail, 'damaged_qty' => $newDamaged])->save();

            $ref = $o['reference'] ?? null;
            return InventoryTransaction::create([
                'product_id' => $product->id, 'type' => $type,
                'qty_change' => $dAvail, 'damaged_change' => $dDamaged, 'balance_after' => $newAvail,
                'unit_cost' => $o['unit_cost'] ?? null,
                'reference_type' => $ref?->getMorphClass(), 'reference_id' => $ref?->getKey(),
                'note' => $o['note'] ?? null, 'created_by' => auth()->id(),
            ]);
        });
    }

    /** Stock row for a product; new rows get the default minimum stock from Settings. */
    public function ensureRow(Product $product): Inventory
    {
        return Inventory::firstOrCreate(['product_id' => $product->id], ['minimum_stock' => max(0, (int) SettingsService::get('default_minimum_stock', 0))]);
    }

    /** Integrity check: bucket must equal sum of ledger. Returns inventory rows that disagree. */
    public function mismatches(): Collection
    {
        return Inventory::query()->select('inventory.*')
            ->selectSub(InventoryTransaction::query()->selectRaw('COALESCE(SUM(qty_change),0)')->whereColumn('product_id', 'inventory.product_id'), 'ledger_avail')
            ->selectSub(InventoryTransaction::query()->selectRaw('COALESCE(SUM(damaged_change),0)')->whereColumn('product_id', 'inventory.product_id'), 'ledger_damaged')
            ->get()
            ->filter(fn ($r) => (int) $r->ledger_avail !== (int) $r->available_qty || (int) $r->ledger_damaged !== (int) $r->damaged_qty)
            ->values();
    }
}
