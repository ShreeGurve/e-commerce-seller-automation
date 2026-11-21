<?php

namespace App\Services;

use App\Marketplaces\Sync\MarketplaceReadProviderRegistry;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventorySnapshot;
use App\Models\MarketplaceSyncRecord;
use App\Models\MarketplaceSyncRun;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MarketplaceReadSyncService
{
    private const RESOURCES = ['products', 'orders', 'inventory'];

    public function __construct(private readonly MarketplaceReadProviderRegistry $providers) {}

    public function sync(
        MarketplaceConnection $connection,
        string $resource,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): MarketplaceSyncRun {
        $resource = strtolower(trim($resource));
        if (! in_array($resource, self::RESOURCES, true)) {
            throw new RuntimeException('Unsupported read-sync resource. Allowed: products, orders, inventory.');
        }
        if ($from && $to && $from > $to) {
            throw new RuntimeException('Sync start date must not be after the end date.');
        }

        $connection->loadMissing(['marketplace', 'business']);
        if (! $connection->marketplace || ! $connection->business) {
            throw new RuntimeException('Marketplace connection is missing its business or marketplace.');
        }
        if (! in_array(strtolower((string) $connection->status), ['connected', 'active', 'verified'], true)) {
            throw new RuntimeException('Marketplace connection must be verified before synchronization.');
        }

        $lockKey = 'marketplace-read-sync:'.$connection->id.':'.$resource;
        $lock = Cache::lock($lockKey, max(60, (int) config('marketplace_sync.lock_seconds', 900)));
        if (! $lock->get()) {
            throw new RuntimeException('A synchronization for this connection and resource is already running.');
        }

        $run = MarketplaceSyncRun::create([
            'business_id' => $connection->business_id,
            'marketplace_connection_id' => $connection->id,
            'marketplace_id' => $connection->marketplace_id,
            'resource' => $resource,
            'status' => 'RUNNING',
            'requested_from' => $from?->format('Y-m-d'),
            'requested_to' => $to?->format('Y-m-d'),
            'started_at' => now(),
        ]);

        try {
            $marketplaceCode = strtolower((string) $connection->marketplace->code);
            $apiConfig = config('marketplaces.'.$marketplaceCode, []);
            if (! ($apiConfig['enabled'] ?? false) || ! ($apiConfig['api_calls_enabled'] ?? false)) {
                throw new RuntimeException('Marketplace read API calls are disabled by configuration.');
            }
            $provider = $this->providers->for($marketplaceCode);
            $cursor = null;
            $seenCursors = [];
            $maxPages = max(1, (int) config('marketplace_sync.max_pages_per_run', 100));

            do {
                $page = $provider->fetchPage($connection, $resource, $from, $to, $cursor);
                $run->pages_processed++;

                DB::transaction(function () use ($page, $connection, $resource, $run): void {
                    foreach ($page->items as $item) {
                        if (! is_array($item) || ! isset($item['external_id'], $item['payload'])
                            || ! is_array($item['payload']) || trim((string) $item['external_id']) === '') {
                            $run->records_skipped++;

                            continue;
                        }

                        $externalId = Str::limit(trim((string) $item['external_id']), 150, '');
                        if ($externalId === '') {
                            $run->records_skipped++;

                            continue;
                        }

                        $payload = $this->removePersonalData($item['payload']);
                        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                        $existing = MarketplaceSyncRecord::query()
                            ->where('marketplace_connection_id', $connection->id)
                            ->where('resource', $resource)
                            ->where('external_id', $externalId)
                            ->first();

                        MarketplaceSyncRecord::updateOrCreate(
                            [
                                'marketplace_connection_id' => $connection->id,
                                'resource' => $resource,
                                'external_id' => $externalId,
                            ],
                            [
                                'business_id' => $connection->business_id,
                                'marketplace_id' => $connection->marketplace_id,
                                'sync_run_id' => $run->id,
                                'payload' => $payload,
                                'fingerprint' => $fingerprint,
                                'first_seen_at' => $existing?->first_seen_at ?? now(),
                                'last_seen_at' => now(),
                            ]
                        );

                        $existing ? $run->records_updated++ : $run->records_created++;
                        $run->records_fetched++;

                        if ($resource === 'inventory') {
                            $this->storeInventorySnapshot($connection, $payload);
                        }
                    }
                });

                if (! $page->hasMore) {
                    break;
                }
                if (! $page->nextCursor || isset($seenCursors[$page->nextCursor])) {
                    throw new RuntimeException('Marketplace pagination returned a missing or repeated cursor.');
                }
                $seenCursors[$page->nextCursor] = true;
                $cursor = $page->nextCursor;
            } while ($run->pages_processed < $maxPages);

            if ($page->hasMore && $run->pages_processed >= $maxPages) {
                $run->status = 'PARTIAL';
                $run->error_message = 'Maximum pages per run reached. Start another run to continue fetching.';
            } else {
                $run->status = 'COMPLETED';
            }
        } catch (Throwable $exception) {
            $run->status = 'FAILED';
            $run->error_message = Str::limit($exception->getMessage(), 1000);
        } finally {
            $run->finished_at = now();
            $run->save();
            $lock->release();
        }

        return $run->refresh();
    }

    private function storeInventorySnapshot(MarketplaceConnection $connection, array $payload): void
    {
        $sku = trim((string) ($payload['marketplace_sku'] ?? $payload['sku'] ?? ''));
        if ($sku === '') {
            return;
        }

        MarketplaceInventorySnapshot::updateOrCreate(
            [
                'marketplace_connection_id' => $connection->id,
                'marketplace_sku' => Str::limit($sku, 120, ''),
                'fulfillment_channel' => Str::upper(Str::limit((string) ($payload['fulfillment_channel'] ?? 'UNKNOWN'), 30, '')),
            ],
            [
                'business_id' => $connection->business_id,
                'marketplace_id' => $connection->marketplace_id,
                'available_qty' => max(0, (int) ($payload['available_qty'] ?? $payload['available'] ?? 0)),
                'reserved_qty' => max(0, (int) ($payload['reserved_qty'] ?? $payload['reserved'] ?? 0)),
                'inbound_qty' => max(0, (int) ($payload['inbound_qty'] ?? $payload['inbound'] ?? 0)),
                'snapshot_at' => now(),
                'raw_payload' => $payload,
            ]
        );
    }

    /** Remove common customer/buyer PII keys before staging marketplace order payloads. */
    private function removePersonalData(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (preg_match('/customer|buyer|recipient|address|phone|mobile|email|personal|tax.?id|contact.?name/', $normalized)) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->removePersonalData($value) : $value;
        }

        return $clean;
    }
}
