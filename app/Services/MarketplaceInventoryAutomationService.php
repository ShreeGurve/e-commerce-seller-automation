<?php

namespace App\Services;

use App\Jobs\RunMarketplaceInventoryAutomation;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryAlert;
use App\Models\MarketplaceInventoryAutomationRun;
use App\Models\MarketplaceInventoryAutomationSetting;
use App\Models\MarketplaceInventoryReconciliationRun;
use App\Models\MarketplaceSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class MarketplaceInventoryAutomationService
{
    public function __construct(
        private readonly MarketplaceReadSyncService $sync,
        private readonly MarketplaceInventoryReconciliationService $reconciliation,
    ) {}

    /** Dispatches only enabled settings that are due. Safe to call hourly from Laravel Scheduler. */
    public function scheduleDue(): int
    {
        if (! (bool) config('marketplace_sync.inventory_automation.enabled', true)) {
            return 0;
        }
        $scheduled = 0;
        MarketplaceInventoryAutomationSetting::query()
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($settings) use (&$scheduled): void {
                foreach ($settings as $setting) {
                    $lock = Cache::lock('marketplace-inventory-automation-schedule:'.$setting->marketplace_connection_id, 60);
                    if (! $lock->get()) { continue; }
                    try {
                        $fresh = MarketplaceInventoryAutomationSetting::query()->find($setting->id);
                        if (! $fresh || ! $fresh->enabled || ($fresh->next_run_at && $fresh->next_run_at->isFuture())) { continue; }
                        $connection = MarketplaceConnection::query()->find($fresh->marketplace_connection_id);
                        if (! $connection || ! in_array(strtolower((string) $connection->status), ['connected', 'active', 'verified'], true)) { continue; }
                        RunMarketplaceInventoryAutomation::dispatch((int) $connection->id, (int) $fresh->id);
                        $fresh->last_scheduled_at = now();
                        $fresh->next_run_at = now()->addHours(max(1, min(168, (int) $fresh->interval_hours)));
                        $fresh->save();
                        $scheduled++;
                    } finally {
                        $lock->release();
                    }
                }
            });

        return $scheduled;
    }

    public function run(int $connectionId, int $settingId): MarketplaceInventoryAutomationRun
    {
        $connection = MarketplaceConnection::query()->with('marketplace')->findOrFail($connectionId);
        $setting = MarketplaceInventoryAutomationSetting::query()->whereKey($settingId)
            ->where('marketplace_connection_id', $connectionId)->firstOrFail();

        $run = MarketplaceInventoryAutomationRun::create([
            'business_id' => $connection->business_id,
            'marketplace_connection_id' => $connection->id,
            'status' => 'RUNNING',
            'started_at' => now(),
        ]);

        try {
            $syncRun = $this->sync->sync($connection, 'inventory');
            $run->sync_run_id = $syncRun->id;
            if ($syncRun->status !== 'COMPLETED') {
                throw new RuntimeException($syncRun->error_message ?: 'Inventory sync did not complete; reconciliation was skipped.');
            }

            $reconciliationRun = $this->reconciliation->reconcile($connection);
            $run->reconciliation_run_id = $reconciliationRun->id;
            $this->evaluateAlerts($reconciliationRun, $setting);
            $run->status = 'COMPLETED';
            $run->message = sprintf('Inventory sync and reconciliation completed. %d SKUs checked.', $reconciliationRun->items_checked);
        } catch (Throwable $exception) {
            $run->status = 'FAILED';
            $run->message = mb_substr($exception->getMessage(), 0, 2000);
            $this->recordSyncFailure($connection, $exception->getMessage());
        } finally {
            $run->finished_at = now();
            $run->save();
        }

        return $run->refresh();
    }

    /** Alert upserts are idempotent. No stock, listing, price, or order writes occur here. */
    public function evaluateAlerts(MarketplaceInventoryReconciliationRun $reconciliationRun, MarketplaceInventoryAutomationSetting $setting): void
    {
        $reconciliationRun->loadMissing('items');
        $activeKeys = [];
        foreach ($reconciliationRun->items as $item) {
            $skuKey = hash('sha256', (string) $item->marketplace_sku);
            if ($item->status === 'MISMATCH' && $item->correction_eligible) {
                $type = ((int) $item->marketplace_qty < (int) $item->internal_qty) ? 'OVERSELL_RISK' : 'INVENTORY_MISMATCH';
                $this->upsertAlert($reconciliationRun, $type, $skuKey, $item->marketplace_sku, $type === 'OVERSELL_RISK' ? 'critical' : 'warning', [
                    'marketplace_qty' => (int) $item->marketplace_qty,
                    'internal_qty' => (int) $item->internal_qty,
                    'variance' => (int) $item->variance,
                    'fulfillment_channel' => $item->fulfillment_channel,
                ]);
                $activeKeys[] = $type.':'.$skuKey;
            }
            if ($item->status !== 'UNMAPPED' && (int) $item->marketplace_qty <= (int) $setting->low_stock_threshold) {
                $this->upsertAlert($reconciliationRun, 'LOW_STOCK', $skuKey, $item->marketplace_sku, 'warning', [
                    'marketplace_qty' => (int) $item->marketplace_qty,
                    'threshold' => (int) $setting->low_stock_threshold,
                    'fulfillment_channel' => $item->fulfillment_channel,
                ]);
                $activeKeys[] = 'LOW_STOCK:'.$skuKey;
            }
        }

        $query = MarketplaceInventoryAlert::query()
            ->where('marketplace_connection_id', $reconciliationRun->marketplace_connection_id)
            ->where('status', 'OPEN')
            ->whereIn('type', ['LOW_STOCK', 'OVERSELL_RISK', 'INVENTORY_MISMATCH']);
        foreach ($query->get() as $alert) {
            if (! in_array($alert->type.':'.hash('sha256', (string) $alert->marketplace_sku), $activeKeys, true)) {
                $alert->status = 'RESOLVED';
                $alert->resolved_at = now();
                $alert->save();
            }
        }
    }

    private function upsertAlert(MarketplaceInventoryReconciliationRun $run, string $type, string $skuKey, string $sku, string $severity, array $payload): void
    {
        $key = $type.':'.$skuKey;
        $existing = MarketplaceInventoryAlert::query()
            ->where('marketplace_connection_id', $run->marketplace_connection_id)
            ->where('alert_key', $key)->first();
        MarketplaceInventoryAlert::updateOrCreate(
            ['marketplace_connection_id' => $run->marketplace_connection_id, 'alert_key' => $key],
            [
                'business_id' => $run->business_id,
                'type' => $type,
                'severity' => $severity,
                'status' => $existing && $existing->status === 'ACKNOWLEDGED' ? 'ACKNOWLEDGED' : 'OPEN',
                'marketplace_sku' => $sku,
                'payload' => $payload,
                'first_seen_at' => $existing?->first_seen_at ?? now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]
        );
    }

    private function recordSyncFailure(MarketplaceConnection $connection, string $message): void
    {
        $key = 'SYNC_FAILURE';
        $existing = MarketplaceInventoryAlert::query()->where('marketplace_connection_id', $connection->id)->where('alert_key', $key)->first();
        MarketplaceInventoryAlert::updateOrCreate(
            ['marketplace_connection_id' => $connection->id, 'alert_key' => $key],
            [
                'business_id' => $connection->business_id,
                'type' => 'SYNC_FAILURE',
                'severity' => 'critical',
                'status' => $existing && $existing->status === 'ACKNOWLEDGED' ? 'ACKNOWLEDGED' : 'OPEN',
                'marketplace_sku' => null,
                'payload' => ['message' => mb_substr($message, 0, 1000)],
                'first_seen_at' => $existing?->first_seen_at ?? now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
            ]
        );
    }
}
