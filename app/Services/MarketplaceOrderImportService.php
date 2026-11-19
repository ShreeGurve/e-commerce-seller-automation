<?php

namespace App\Services;

use App\Models\MarketplaceOrderImport;
use App\Models\MarketplaceSyncRecord;
use App\Models\Product;
use App\Models\ProductMarketplace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MarketplaceOrderImportService
{
    public function previewFromSyncRun(int $syncRunId, int $businessId): int
    {
        $records = MarketplaceSyncRecord::query()
            ->where('sync_run_id', $syncRunId)
            ->where('business_id', $businessId)
            ->where('resource', 'orders')
            ->orderBy('id')
            ->get();

        $created = 0;
        foreach ($records as $record) {
            $connection = $record->connection;
            if (!$connection || (int) $connection->business_id !== $businessId) continue;
            $externalId = trim((string) ($record->payload['order_id'] ?? $record->external_id));
            if ($externalId === '' || strlen($externalId) > 60) continue;

            $preview = $this->buildPreview($record->payload, $connection->marketplace_id);
            $existing = MarketplaceOrderImport::query()
                ->where('marketplace_connection_id', $connection->id)
                ->where('external_order_id', $externalId)->first();

            if ($existing) {
                if (in_array($existing->status, ['IMPORTED', 'REJECTED'], true)) continue;
                $existing->update([
                    'sync_record_id' => $record->id,
                    'preview_payload' => $preview['payload'],
                    'validation_errors' => $preview['errors'],
                    'status' => 'PENDING',
                ]);
                continue;
            }

            MarketplaceOrderImport::create([
                'business_id' => $businessId,
                'marketplace_connection_id' => $connection->id,
                'marketplace_id' => $connection->marketplace_id,
                'sync_record_id' => $record->id,
                'external_order_id' => $externalId,
                'status' => 'PENDING',
                'preview_payload' => $preview['payload'],
                'validation_errors' => $preview['errors'],
                'deduct_stock' => false,
            ]);
            $created++;
        }

        return $created;
    }

    public function approveAndImport(MarketplaceOrderImport $candidate, User $reviewer, bool $deductStock = false): MarketplaceOrderImport
    {
        try {
            return DB::transaction(function () use ($candidate, $reviewer, $deductStock) {
            $row = MarketplaceOrderImport::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ((int) $row->business_id !== (int) $reviewer->business_id) {
                throw ValidationException::withMessages(['import' => 'This order import belongs to another business.']);
            }
            if ($row->status === 'IMPORTED') return $row->load('order');
            if ($row->status !== 'PENDING') {
                throw ValidationException::withMessages(['status' => 'Only pending order imports can be approved.']);
            }
            $errors = $row->validation_errors ?? [];
            if ($errors !== []) {
                throw ValidationException::withMessages(['mapping' => 'Resolve all product and data validation issues before approving this order.']);
            }

            $payload = $row->preview_payload ?? [];
            $items = $payload['items'] ?? [];
            if ($items === []) throw ValidationException::withMessages(['items' => 'The marketplace order has no importable line items.']);

            $orderService = app(OrderService::class);
            $order = $orderService->create([
                'marketplace_id' => $row->marketplace_id,
                'external_order_id' => $row->external_order_id,
                'order_date' => $payload['order_date'],
                'status' => 'IMPORTED',
                'marketplace_status' => $payload['marketplace_status'] ?? null,
                'fulfillment_type' => $payload['fulfillment_type'] ?? 'SELF',
                'source' => 'API',
                'deduct_stock' => $deductStock && ($payload['fulfillment_type'] ?? 'SELF') === 'SELF',
                'notes' => 'Imported from marketplace after manual approval. Customer personal data was not requested or retained.',
            ], $items, $reviewer);

            $row->update([
                'status' => 'IMPORTED',
                'order_id' => $order->id,
                'deduct_stock' => $deductStock && ($payload['fulfillment_type'] ?? 'SELF') === 'SELF',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'imported_at' => now(),
                'failure_message' => null,
            ]);

            return $row->fresh(['order', 'reviewer']);
            });
        } catch (Throwable $exception) {
            MarketplaceOrderImport::query()->whereKey($candidate->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->update([
                    'status' => 'FAILED',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'failure_message' => Str::limit($exception->getMessage(), 1000),
                    'updated_at' => now(),
                ]);
            throw $exception;
        }
    }

    public function reject(MarketplaceOrderImport $candidate, User $reviewer, ?string $reason = null): MarketplaceOrderImport
    {
        if ((int) $candidate->business_id !== (int) $reviewer->business_id) {
            throw ValidationException::withMessages(['import' => 'This order import belongs to another business.']);
        }
        if ($candidate->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => 'Only pending order imports can be rejected.']);
        }
        $candidate->update([
            'status' => 'REJECTED',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'failure_message' => $reason ? Str::limit($reason, 1000) : null,
        ]);
        return $candidate->fresh();
    }

    private function buildPreview(array $source, int $marketplaceId): array
    {
        $errors = [];
        $orderDate = $source['created_at'] ?? $source['order_date'] ?? null;
        try { $orderDate = $orderDate ? now()->parse($orderDate)->toDateTimeString() : null; }
        catch (Throwable) { $orderDate = null; }
        if (!$orderDate) $errors[] = 'Marketplace order date is missing or invalid.';

        $sourceItems = $source['items'] ?? [];
        if (!is_array($sourceItems) || $sourceItems === []) $errors[] = 'Marketplace order contains no line items.';
        $items = [];
        foreach (is_array($sourceItems) ? $sourceItems : [] as $index => $line) {
            if (!is_array($line)) { $errors[] = 'Order line '.($index + 1).' is invalid.'; continue; }
            $sku = trim((string) ($line['sku'] ?? $line['seller_sku'] ?? ''));
            $quantity = (int) ($line['quantity'] ?? 0);
            $price = $line['unit_price'] ?? null;
            $currency = strtoupper((string) ($line['currency'] ?? 'INR'));
            if ($sku === '' || $quantity < 1 || !is_numeric($price) || (float) $price < 0) {
                $errors[] = 'Order line '.($index + 1).' requires a mapped SKU, positive quantity and valid unit price.';
                continue;
            }
            if ($currency !== 'INR') {
                $errors[] = 'Order line '.($index + 1).' uses '.$currency.'; only INR orders can be imported into this INR ledger.';
                continue;
            }
            $mapping = ProductMarketplace::query()
                ->where('marketplace_id', $marketplaceId)
                ->where('marketplace_sku', $sku)
                ->first();
            if (!$mapping) {
                $errors[] = 'No product mapping exists for marketplace SKU '.$sku.'.';
                continue;
            }
            $product = Product::query()->whereKey($mapping->product_id)->where('status', 'active')->first();
            if (!$product) {
                $errors[] = 'Mapped product for marketplace SKU '.$sku.' is missing, deleted, or inactive.';
                continue;
            }
            $items[] = [
                'product_id' => $mapping->product_id,
                'external_line_id' => isset($line['order_item_id']) ? (string) $line['order_item_id'] : (string) ($index + 1),
                'marketplace_sku' => $sku,
                'quantity' => $quantity,
                'unit_price' => number_format((float) $price, 2, '.', ''),
            ];
        }
        if ($items === [] && $errors === []) $errors[] = 'No valid order lines were found.';

        $fulfilledBy = strtoupper((string) ($source['fulfilled_by'] ?? ''));
        $fulfillmentType = in_array($fulfilledBy, ['AMAZON', 'AFN', 'FBA', 'FLIPKART', 'MARKETPLACE'], true) ? 'MARKETPLACE' : 'SELF';
        return [
            'payload' => [
                'order_date' => $orderDate,
                'marketplace_status' => isset($source['fulfillment_status']) ? Str::limit((string) $source['fulfillment_status'], 60, '') : null,
                'fulfillment_type' => $fulfillmentType,
                'items' => $items,
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }
}
