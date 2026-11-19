<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryCorrectionRequest;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryReconciliationItem;
use App\Models\MarketplaceInventoryReconciliationRun;
use App\Models\MarketplaceInventorySnapshot;
use App\Models\ProductMarketplace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MarketplaceInventoryReconciliationService
{
    /** Only explicitly seller-managed stock channels can propose an internal stock correction. */
    private const SELLER_MANAGED_CHANNELS = ['FBM', 'MFN', 'SELLER', 'SELLER_FULFILLED', 'MERCHANT'];

    public function reconcile(MarketplaceConnection $connection): MarketplaceInventoryReconciliationRun
    {
        $connection->loadMissing('marketplace', 'business');
        if (! $connection->marketplace || ! $connection->business) {
            throw new RuntimeException('Marketplace connection is missing its business or marketplace.');
        }
        if (! in_array(strtolower((string) $connection->status), ['connected', 'active', 'verified'], true)) {
            throw new RuntimeException('Marketplace connection must be verified before inventory reconciliation.');
        }

        return DB::transaction(function () use ($connection) {
            $snapshots = MarketplaceInventorySnapshot::query()
                ->where('marketplace_connection_id', $connection->id)
                ->orderBy('id')
                ->get();

            $run = MarketplaceInventoryReconciliationRun::create([
                'business_id' => $connection->business_id,
                'marketplace_connection_id' => $connection->id,
                'marketplace_id' => $connection->marketplace_id,
                'status' => 'RUNNING',
                'snapshot_cutoff_at' => $snapshots->max('snapshot_at'),
            ]);

            foreach ($snapshots as $snapshot) {
                $mapping = ProductMarketplace::query()
                    ->with(['product.inventory'])
                    ->where('marketplace_id', $connection->marketplace_id)
                    ->where('marketplace_sku', $snapshot->marketplace_sku)
                    ->first();

                $channel = strtoupper(trim((string) $snapshot->fulfillment_channel));
                $marketplaceQty = max(0, (int) $snapshot->available_qty);
                $eligible = in_array($channel, self::SELLER_MANAGED_CHANNELS, true);

                if (! $mapping || ! $mapping->product) {
                    $status = 'UNMAPPED';
                    $internalQty = null;
                    $variance = null;
                    $productId = null;
                } else {
                    $productId = $mapping->product_id;
                    $internalQty = (int) ($mapping->product->inventory?->available_qty ?? 0);
                    $variance = $marketplaceQty - $internalQty;
                    if ($variance === 0) {
                        $status = 'MATCHED';
                    } elseif (! $eligible) {
                        $status = 'MANAGED_CHANNEL';
                    } else {
                        $status = 'MISMATCH';
                    }
                }

                MarketplaceInventoryReconciliationItem::create([
                    'reconciliation_run_id' => $run->id,
                    'inventory_snapshot_id' => $snapshot->id,
                    'product_id' => $productId,
                    'marketplace_sku' => $snapshot->marketplace_sku,
                    'fulfillment_channel' => $channel ?: 'UNKNOWN',
                    'marketplace_qty' => $marketplaceQty,
                    'internal_qty' => $internalQty,
                    'variance' => $variance,
                    'status' => $status,
                    'correction_eligible' => $eligible && $status === 'MISMATCH',
                    'snapshot_at' => $snapshot->snapshot_at,
                ]);

                $run->items_checked++;
                match ($status) {
                    'MATCHED' => $run->matched_count++,
                    'UNMAPPED' => $run->unmapped_count++,
                    'MANAGED_CHANNEL' => $run->managed_channel_count++,
                    default => $run->mismatch_count++,
                };
            }

            $run->status = 'COMPLETED';
            $run->completed_at = now();
            $run->save();

            return $run->refresh();
        });
    }

    /** Creates a pending request in the existing inventory-correction approval workflow; never changes stock here. */
    public function requestCorrection(MarketplaceInventoryReconciliationItem $item, User $user): InventoryCorrectionRequest
    {
        return DB::transaction(function () use ($item, $user) {
            $locked = MarketplaceInventoryReconciliationItem::query()
                ->with(['run.connection.marketplace', 'product'])
                ->lockForUpdate()->findOrFail($item->id);

            if (! $locked->correction_eligible || $locked->status !== 'MISMATCH' || ! $locked->product) {
                throw ValidationException::withMessages(['inventory' => 'This row is not eligible for a stock correction. Marketplace-managed and unmapped inventory cannot create internal stock corrections.']);
            }
            if ($locked->run->business_id !== $user->business_id) {
                throw ValidationException::withMessages(['inventory' => 'This reconciliation belongs to another business.']);
            }
            if ($locked->inventory_correction_request_id) {
                throw ValidationException::withMessages(['inventory' => 'A correction request already exists for this reconciliation row.']);
            }

            $maxAgeHours = max(1, (int) config('marketplace_sync.reconciliation_max_snapshot_age_hours', 24));
            if (! $locked->snapshot_at || $locked->snapshot_at->lt(now()->subHours($maxAgeHours))) {
                throw ValidationException::withMessages(['inventory' => 'The marketplace inventory snapshot is too old. Run a fresh inventory sync before requesting a correction.']);
            }

            $inventory = Inventory::query()->where('product_id', $locked->product_id)->lockForUpdate()->first();
            $systemQty = (int) ($inventory?->available_qty ?? 0);
            $variance = (int) $locked->marketplace_qty - $systemQty;
            if ($variance === 0) {
                throw ValidationException::withMessages(['inventory' => 'Internal stock now matches the marketplace quantity. Run reconciliation again.']);
            }
            if (InventoryCorrectionRequest::query()->where('product_id', $locked->product_id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['inventory' => 'A stock correction is already pending for this product.']);
            }

            $marketplaceName = (string) ($locked->run->connection->marketplace->name ?? 'Marketplace');
            $request = InventoryCorrectionRequest::create([
                'product_id' => $locked->product_id,
                'requested_by' => $user->id,
                'status' => 'pending',
                'system_qty' => $systemQty,
                'counted_qty' => (int) $locked->marketplace_qty,
                'variance' => $variance,
                'note' => sprintf('%s inventory reconciliation for SKU %s (%s). Confirm seller-managed stock before approval.', $marketplaceName, $locked->marketplace_sku, $locked->fulfillment_channel),
            ]);

            $locked->inventory_correction_request_id = $request->id;
            $locked->status = 'CORRECTION_REQUESTED';
            $locked->save();

            return $request;
        });
    }
}
