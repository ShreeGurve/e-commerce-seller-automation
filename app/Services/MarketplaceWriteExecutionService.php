<?php

namespace App\Services;

use App\Marketplaces\Writes\AmazonListingsWriteAdapter;
use App\Marketplaces\Writes\FlipkartListingsWriteAdapter;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MarketplaceWriteExecutionService
{
    public const OPERATIONS = ['PRICE_UPDATE', 'INVENTORY_UPDATE'];

    public function dashboard(int $businessId): array
    {
        $connections = MarketplaceConnection::with('marketplace')->where('business_id', $businessId)->orderBy('id')->get();
        $policies = DB::table('marketplace_write_policies as p')
            ->join('marketplace_connections as c', 'c.id', '=', 'p.marketplace_connection_id')
            ->where('c.business_id', $businessId)
            ->select('p.*')->get()->keyBy(fn ($row) => $row->marketplace_connection_id.'_'.$row->operation);
        $tasks = DB::table('marketplace_automation_tasks as t')
            ->join('products as p', 'p.id', '=', 't.product_id')
            ->join('users as owner', 'owner.id', '=', 'p.created_by')
            ->where('owner.business_id', $businessId)
            ->where('t.status', 'APPROVED')
            ->select('t.id', 't.title', 't.recommendation_type', 't.product_id', 'p.sku as internal_sku', 'p.name as product_name')
            ->orderByDesc('t.updated_at')->limit(100)->get();
        $attempts = DB::table('marketplace_write_attempts as a')
            ->leftJoin('users as preparer', 'preparer.id', '=', 'a.prepared_by')
            ->where('a.business_id', $businessId)
            ->select('a.*', 'preparer.name as prepared_by_name')
            ->orderByDesc('a.id')->limit(100)->get();

        return compact('connections', 'policies', 'tasks', 'attempts');
    }

    public function setPolicy(int $connectionId, string $operation, bool $enabled, User $user): void
    {
        $operation = strtoupper($operation);
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw ValidationException::withMessages(['operation' => 'Unsupported marketplace write operation.']);
        }
        $connection = MarketplaceConnection::with('marketplace')->where('business_id', $user->business_id)->findOrFail($connectionId);
        $this->assertSupported($connection, $operation);
        if ($enabled && $connection->status !== 'connected') {
            throw new RuntimeException('Only a verified, connected marketplace account can have a live write policy enabled.');
        }
        if ($enabled && ($connection->environment ?? 'production') !== 'production') {
            throw new RuntimeException('Live write policies can only be enabled for Production connections.');
        }
        if ($enabled && !$user->isSuperAdmin()) {
            throw new RuntimeException('Only a Super Admin can enable a live marketplace write policy.');
        }

        $policyQuery = DB::table('marketplace_write_policies')
            ->where('marketplace_connection_id', $connection->id)->where('operation', $operation);
        $values = [
            'is_enabled' => $enabled,
            'updated_by' => $user->id,
            'enabled_at' => $enabled ? now() : null,
            'updated_at' => now(),
        ];
        if ($policyQuery->exists()) {
            $policyQuery->update($values);
        } else {
            DB::table('marketplace_write_policies')->insert($values + [
                'marketplace_connection_id' => $connection->id,
                'operation' => $operation,
                'created_at' => now(),
            ]);
        }
    }

    /** Build an immutable, local-only preview. No marketplace API request is made here. */
    public function prepare(User $user, array $data): object
    {
        $operation = strtoupper($data['operation']);
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw ValidationException::withMessages(['operation' => 'Unsupported marketplace write operation.']);
        }

        $connection = MarketplaceConnection::with('marketplace')
            ->where('business_id', $user->business_id)->findOrFail((int) $data['connection_id']);
        if ($connection->status !== 'connected') {
            throw ValidationException::withMessages(['connection_id' => 'Select a verified, connected marketplace account.']);
        }
        $this->assertSupported($connection, $operation);

        $task = DB::table('marketplace_automation_tasks')->where('id', (int) $data['task_id'])->first();
        if (!$task || $task->status !== 'APPROVED' || !$task->product_id || !$task->reviewed_by || !$task->reviewed_at) {
            throw ValidationException::withMessages(['task_id' => 'A marketplace automation task linked to a product must be formally APPROVED with reviewer and timestamp.']);
        }
        $allowedTypes = $operation === 'PRICE_UPDATE' ? ['LOW_PROFIT_MARGIN'] : ['RESTOCK', 'STOCK_RISK'];
        if (!in_array($task->recommendation_type, $allowedTypes, true)) {
            throw ValidationException::withMessages(['task_id' => 'The approved task type does not authorize this operation.']);
        }

        $mapping = DB::table('product_marketplace')
            ->where('product_id', $task->product_id)
            ->where('marketplace_id', $connection->marketplace_id)
            ->first();
        if (!$mapping || trim((string) $mapping->marketplace_sku) === '') {
            throw ValidationException::withMessages(['task_id' => 'No marketplace SKU mapping exists for this product and connection.']);
        }
        $product = DB::table('products')->where('id', $task->product_id)->first();
        if (!$product) {
            throw ValidationException::withMessages(['task_id' => 'The linked product no longer exists.']);
        }
        $productOwner = $product->created_by ? DB::table('users')->where('id', $product->created_by)->value('business_id') : null;
        if ((int) $productOwner !== (int) $user->business_id) {
            throw ValidationException::withMessages(['task_id' => 'The selected product is not owned by your business. Marketplace writes are blocked.']);
        }

        $payload = $this->buildPayload($connection, $operation, $mapping, $product, $data);
        $payloadJson = $this->canonicalJson($payload);
        $hash = hash('sha256', $payloadJson);
        $key = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        if (strlen($key) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key may not exceed 100 characters.']);
        }

        $existing = DB::table('marketplace_write_attempts')->where('idempotency_key', $key)->first();
        if ($existing) {
            if (!hash_equals((string) $existing->payload_hash, $hash) || (int) $existing->task_id !== (int) $task->id
                || (int) $existing->marketplace_connection_id !== (int) $connection->id || $existing->operation !== $operation) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different payload.']);
            }
            return $existing;
        }

        $id = DB::table('marketplace_write_attempts')->insertGetId([
            'business_id' => $user->business_id,
            'marketplace_connection_id' => $connection->id,
            'task_id' => $task->id,
            'marketplace_code' => $connection->marketplace->code,
            'operation' => $operation,
            'status' => 'PREPARED',
            'marketplace_sku' => $mapping->marketplace_sku,
            'idempotency_key' => $key,
            'payload_hash' => $hash,
            'request_payload' => $payloadJson,
            'prepared_by' => $user->id,
            'expires_at' => now()->addMinutes(max(5, min(120, (int) config('marketplace_writes.attempt_ttl_minutes', 30)))),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('marketplace_write_attempts')->where('id', $id)->first();
    }

    /** Execute only after a second user approves the exact, hashed payload. */
    public function approveAndExecute(int $attemptId, User $approver): object
    {
        $expired = false;
        $attempt = DB::transaction(function () use ($attemptId, $approver, &$expired) {
            $row = DB::table('marketplace_write_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            if (!$row || (int) $row->business_id !== (int) $approver->business_id) {
                throw ValidationException::withMessages(['attempt' => 'Write attempt was not found.']);
            }
            if ($row->status !== 'PREPARED') {
                throw ValidationException::withMessages(['attempt' => 'Only a PREPARED attempt can be approved and submitted.']);
            }
            if ($row->expires_at && now()->greaterThan($row->expires_at)) {
                DB::table('marketplace_write_attempts')->where('id', $row->id)->update(['status' => 'EXPIRED', 'updated_at' => now()]);
                $expired = true;
                return null;
            }
            if ((int) $row->prepared_by === (int) $approver->id) {
                throw ValidationException::withMessages(['approver' => 'A different user must approve this exact write payload.']);
            }
            $payload = json_decode($row->request_payload, true);
            if (!is_array($payload)) {
                throw ValidationException::withMessages(['attempt' => 'The prepared payload is missing or invalid.']);
            }
            $hash = hash('sha256', $this->canonicalJson($payload));
            if (!hash_equals((string) $row->payload_hash, $hash)) {
                throw ValidationException::withMessages(['attempt' => 'The prepared payload failed its integrity check.']);
            }

            $task = DB::table('marketplace_automation_tasks')->where('id', $row->task_id)->first();
            if (!$task || $task->status !== 'APPROVED' || !$task->reviewed_by || !$task->reviewed_at) {
                throw ValidationException::withMessages(['task' => 'The source automation task is no longer formally approved.']);
            }
            $allowedTypes = $row->operation === 'PRICE_UPDATE' ? ['LOW_PROFIT_MARGIN'] : ['RESTOCK', 'STOCK_RISK'];
            if (!in_array($task->recommendation_type, $allowedTypes, true)) {
                throw ValidationException::withMessages(['task' => 'The approved task type no longer matches this marketplace operation.']);
            }
            $policy = DB::table('marketplace_write_policies')->where('marketplace_connection_id', $row->marketplace_connection_id)
                ->where('operation', $row->operation)->first();
            if (!$policy || !$policy->is_enabled) {
                throw ValidationException::withMessages(['policy' => 'Live writes are not enabled for this connection and operation.']);
            }
            $connection = MarketplaceConnection::with('marketplace')->where('business_id', $approver->business_id)->findOrFail($row->marketplace_connection_id);
            if ($connection->marketplace?->code !== $row->marketplace_code) {
                throw ValidationException::withMessages(['connection' => 'Marketplace connection changed after the preview was prepared.']);
            }
            $mappingExists = DB::table('product_marketplace')->where('product_id', $task->product_id)
                ->where('marketplace_id', $connection->marketplace_id)->where('marketplace_sku', $row->marketplace_sku)->exists();
            if (!$mappingExists) {
                throw ValidationException::withMessages(['mapping' => 'The product-to-marketplace SKU mapping changed after preview. Prepare a new attempt.']);
            }
            $product = DB::table('products')->where('id', $task->product_id)->first();
            $productOwner = $product?->created_by ? DB::table('users')->where('id', $product->created_by)->value('business_id') : null;
            if (!$product || (int) $productOwner !== (int) $approver->business_id) {
                throw ValidationException::withMessages(['product' => 'The product is no longer owned by your business.']);
            }
            $this->assertLiveGates($connection);

            DB::table('marketplace_write_attempts')->where('id', $row->id)->update([
                'status' => 'SUBMITTING', 'approved_by' => $approver->id, 'approved_at' => now(),
                'submitted_at' => now(), 'updated_at' => now(),
            ]);
            return (object) array_merge((array) $row, ['request_payload' => $payload, 'connection' => $connection]);
        });

        if ($expired) {
            throw ValidationException::withMessages(['attempt' => 'This preview expired. Prepare a new request.']);
        }

        try {
            $result = match ($attempt->marketplace_code) {
                'amazon' => app(AmazonListingsWriteAdapter::class)->submit($attempt->connection, $attempt->request_payload),
                'flipkart' => app(FlipkartListingsWriteAdapter::class)->submit($attempt->connection, $attempt->request_payload),
                default => throw new RuntimeException('No write adapter is registered for this marketplace.'),
            };
            $httpStatus = (int) ($result['http_status'] ?? 0);
            $status = $result['accepted'] ? 'ACCEPTED' : (($httpStatus >= 500 || $httpStatus === 429) ? 'REVIEW_REQUIRED' : 'FAILED');
            DB::table('marketplace_write_attempts')->where('id', $attempt->id)->update([
                'status' => $status,
                'response_payload' => json_encode($result['body'] ?? [], JSON_UNESCAPED_SLASHES),
                'error_message' => $result['accepted'] ? null : ($result['message'] ?? 'Marketplace rejected the write request.'),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            DB::table('marketplace_write_attempts')->where('id', $attempt->id)->update([
                'status' => 'REVIEW_REQUIRED',
                'error_message' => mb_substr($e->getMessage().' Verify the marketplace listing before preparing another write; the remote outcome may be uncertain.', 0, 4000),
                'updated_at' => now(),
            ]);
        }

        return DB::table('marketplace_write_attempts')->where('id', $attempt->id)->first();
    }

    private function buildPayload(MarketplaceConnection $connection, string $operation, object $mapping, object $product, array $data): array
    {
        $code = (string) $connection->marketplace->code;
        $sku = (string) $mapping->marketplace_sku;
        if ($code === 'amazon') {
            $credentials = $connection->credentials ?? [];
            $marketplaceId = (string) ($credentials['marketplace_id'] ?? '');
            if ($marketplaceId !== 'A21TJRUUN4KGV') {
                throw ValidationException::withMessages(['connection_id' => 'This release supports the Amazon India marketplace only (INR). Other Amazon marketplaces require their own currency mapping.']);
            }
            $sellerId = (string) ($credentials['seller_id'] ?? '');
            $productType = trim((string) ($product->product_type ?? ''));
            if ($marketplaceId === '' || $sellerId === '' || $productType === '') {
                throw ValidationException::withMessages(['connection_id' => 'Amazon seller ID, marketplace ID and product type are required. Complete the connection and product mapping first.']);
            }
            $patch = $operation === 'PRICE_UPDATE'
                ? $this->amazonPricePatch($marketplaceId, $data)
                : $this->amazonInventoryPatch($marketplaceId, $data);
            return ['operation' => $operation, 'sku' => $sku, 'seller_id' => $sellerId, 'marketplace_id' => $marketplaceId, 'product_type' => $productType, 'patches' => [$patch]];
        }

        if ($code === 'flipkart') {
            $externalProductId = (string) ($mapping->external_product_id ?? '');
            if (!preg_match('/^\d{13,16}$/', $externalProductId)) {
                throw ValidationException::withMessages(['task_id' => 'Flipkart product mapping must include a valid 13–16 digit product ID.']);
            }
            if ($operation === 'PRICE_UPDATE') {
                $mrp = filter_var($data['mrp'] ?? null, FILTER_VALIDATE_INT);
                $rawSellingPrice = $data['selling_price'] ?? null;
                $sellingPrice = filter_var($rawSellingPrice, FILTER_VALIDATE_INT);
                if ($mrp === false || $mrp <= 0 || $sellingPrice === false || $sellingPrice <= 0 || $sellingPrice > $mrp) {
                    throw ValidationException::withMessages(['selling_price' => 'Flipkart MRP and selling price must be positive whole rupees; selling price cannot exceed MRP.']);
                }
                $marketplacePayload = ['product_id' => $externalProductId, 'price' => ['mrp' => $mrp, 'sellingPrice' => $sellingPrice, 'currency' => 'INR']];
            } else {
                $locationId = trim((string) ($data['location_id'] ?? ''));
                $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
                if ($locationId === '' || $quantity === false || $quantity < 0) {
                    throw ValidationException::withMessages(['quantity' => 'Flipkart inventory requires a location ID and a non-negative whole-number quantity.']);
                }
                $marketplacePayload = ['product_id' => $externalProductId, 'locations' => [['id' => $locationId, 'inventory' => $quantity]]];
            }
            return ['operation' => $operation, 'sku' => $sku, 'marketplace_payload' => $marketplacePayload];
        }

        throw ValidationException::withMessages(['connection_id' => 'Only Amazon and Flipkart listing writes are supported.']);
    }

    private function amazonPricePatch(string $marketplaceId, array $data): array
    {
        $price = filter_var($data['selling_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price <= 0) {
            throw ValidationException::withMessages(['selling_price' => 'Enter a positive selling price.']);
        }
        return ['op' => 'replace', 'path' => '/attributes/purchasable_offer', 'value' => [[
            'marketplace_id' => $marketplaceId,
            'currency' => 'INR',
            'our_price' => [['schedule' => [['value_with_tax' => (float) $price]]]],
        ]]];
    }

    private function amazonInventoryPatch(string $marketplaceId, array $data): array
    {
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter a non-negative whole-number quantity.']);
        }
        return ['op' => 'replace', 'path' => '/attributes/fulfillment_availability', 'value' => [[
            'fulfillment_channel_code' => 'DEFAULT',
            'quantity' => $quantity,
            'marketplace_id' => $marketplaceId,
        ]]];
    }

    private function canonicalJson(array $payload): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }
            ksort($value);
            foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }
            return $value;
        };
        return json_encode($normalize($payload), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function assertSupported(MarketplaceConnection $connection, string $operation): void
    {
        $code = $connection->marketplace?->code;
        if (!$connection->marketplace || !$connection->marketplace->is_active) {
            throw ValidationException::withMessages(['connection_id' => 'The marketplace platform is inactive.']);
        }
        if (!in_array($code, ['amazon', 'flipkart'], true)) {
            throw ValidationException::withMessages(['connection_id' => 'This marketplace does not have a write adapter.']);
        }
        if ($code === 'flipkart' && ($connection->environment ?? 'production') !== 'production') {
            throw ValidationException::withMessages(['connection_id' => 'Flipkart writes currently require a production connection.']);
        }
    }

    private function assertLiveGates(MarketplaceConnection $connection): void
    {
        if ($connection->status !== 'connected' || ($connection->environment ?? 'production') !== 'production') {
            throw new RuntimeException('Live writes require a verified production marketplace connection.');
        }
        if (!config('marketplace_writes.enabled', false)) {
            throw new RuntimeException('The Package 20 global marketplace write gate is disabled.');
        }
        $code = $connection->marketplace?->code;
        if ($code === 'amazon' && (!config('marketplace_writes.amazon_enabled', false) || !config('marketplaces.amazon.enabled', false) || !config('marketplaces.amazon.api_calls_enabled', false))) {
            throw new RuntimeException('Amazon write/API feature flags are not all enabled.');
        }
        if ($code === 'flipkart' && (!config('marketplace_writes.flipkart_enabled', false) || !config('marketplaces.flipkart.enabled', false) || !config('marketplaces.flipkart.api_calls_enabled', false))) {
            throw new RuntimeException('Flipkart write/API feature flags are not all enabled.');
        }
    }
}
