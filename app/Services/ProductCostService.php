<?php
namespace App\Services;

use App\Models\Product;
use App\Models\ProductCostHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCostService
{
    /**
     * Set a cost from a given date. Old value is preserved: its row is closed the day before.
     * Returns null when nothing changed.
     */
    public function setCost(Product $product, string $type, string|float $amount, ?Carbon $from = null, ?string $note = null): ?ProductCostHistory
    {
        $from = ($from ?? now())->copy()->startOfDay();
        $amount = number_format((float) $amount, 2, '.', '');

        return DB::transaction(function () use ($product, $type, $amount, $from, $note) {
            $open = ProductCostHistory::where('product_id', $product->id)
                ->where('cost_type', $type)->whereNull('effective_to')->lockForUpdate()->first();

            if ($open && (string) $open->amount === $amount) {
                return null;
            }

            if ($open) {
                if ($from->lt($open->effective_from)) {
                    throw ValidationException::withMessages([
                        'effective_from' => 'Date must be on/after the current cost start date (' . $open->effective_from->format('d M Y') . ').',
                    ]);
                }
                // Same-day edit = correction of today's entry
                if ($from->eq($open->effective_from)) {
                    $oldAmount = (string) $open->amount;
                    $open->update(['amount' => $amount, 'note' => $note ?? $open->note]);
                    ActivityLogger::log('cost.corrected', $product, ['type' => $type, 'amount' => $oldAmount], ['type' => $type, 'amount' => $amount]);
                    return $open;
                }
                $open->update(['effective_to' => $from->copy()->subDay()]);
            }

            $row = ProductCostHistory::create([
                'product_id' => $product->id, 'cost_type' => $type, 'amount' => $amount,
                'effective_from' => $from, 'note' => $note, 'created_by' => auth()->id(),
            ]);

            ActivityLogger::log('cost.changed', $product,
                ['type' => $type, 'amount' => $open ? (string) $open->amount : null],
                ['type' => $type, 'amount' => $amount, 'effective_from' => $from->toDateString()]);

            return $row;
        });
    }

    /** Cost in force on a given date (used later by the profit engine). */
    public function costAt(Product $product, string $type, Carbon|string $date): string
    {
        $date = Carbon::parse($date)->toDateString();
        $row = ProductCostHistory::where('product_id', $product->id)->where('cost_type', $type)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();

        return $row ? (string) $row->amount : '0.00';
    }
}
