<?php

namespace App\Marketplaces\Amazon;

use App\Marketplaces\Api\AmazonLwaTokenProvider;
use App\Marketplaces\Sync\MarketplaceReadPage;
use App\Marketplaces\Sync\MarketplaceReadProvider;
use App\Models\MarketplaceConnection;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only Amazon SP-API adapter. It only issues GET requests. */
class AmazonReadProvider implements MarketplaceReadProvider
{
    public function __construct(private readonly AmazonLwaTokenProvider $tokens) {}

    public function fetchPage(
        MarketplaceConnection $connection,
        string $resource,
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        ?string $cursor
    ): MarketplaceReadPage {
        $connection->loadMissing('marketplace');
        if ($connection->marketplace?->code !== 'amazon' || $connection->status !== 'connected') {
            throw new RuntimeException('A connected Amazon seller account is required.');
        }
        if (($connection->environment ?? 'production') !== 'production') {
            throw new RuntimeException('Live Amazon read sync requires a Production connection. Sandbox data is not seller data.');
        }

        $marketplaceId = (string) (($connection->credentials ?? [])['marketplace_id'] ?? '');
        if ($marketplaceId === '') {
            throw new RuntimeException('Amazon marketplace ID is missing from this seller connection. Reconnect the account.');
        }

        return match ($resource) {
            'products' => $this->products($connection, $marketplaceId, $from, $to, $cursor),
            'orders' => $this->orders($connection, $marketplaceId, $from, $to, $cursor),
            'inventory' => $this->inventory($connection, $marketplaceId, $cursor),
            default => throw new RuntimeException('Unsupported Amazon read resource: '.$resource),
        };
    }

    private function products(MarketplaceConnection $connection, string $marketplaceId, ?DateTimeInterface $from, ?DateTimeInterface $to, ?string $cursor): MarketplaceReadPage
    {
        $sellerId = trim((string) (($connection->credentials ?? [])['seller_id'] ?? ''));
        if ($sellerId === '') {
            throw new RuntimeException('Amazon Seller ID is required for product sync. Edit/reconnect this Amazon connection and add the Seller ID.');
        }

        $query = [
            'marketplaceIds' => $marketplaceId,
            'includedData' => 'summaries,fulfillmentAvailability',
            'pageSize' => 20,
            'sortBy' => 'lastUpdatedDate',
            'sortOrder' => 'DESC',
        ];
        if ($from) {
            $query['lastUpdatedAfter'] = $from->format(DATE_ATOM);
        }
        if ($to) {
            $query['lastUpdatedBefore'] = $to->format(DATE_ATOM);
        }
        if ($cursor) {
            $query['pageToken'] = $cursor;
        }

        $data = $this->get($connection, '/listings/2021-08-01/items/'.rawurlencode($sellerId), $query);
        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = (string) ($item['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $summary = $item['summaries'][0] ?? [];
            $items[] = [
                'external_id' => $sku,
                'payload' => [
                    'marketplace_sku' => $sku,
                    'asin' => $summary['asin'] ?? null,
                    'product_title' => $summary['itemName'] ?? null,
                    'condition' => $summary['conditionType'] ?? null,
                    'status' => $summary['status'] ?? null,
                    'fulfillment_availability' => $item['fulfillmentAvailability'] ?? [],
                ],
            ];
        }

        return new MarketplaceReadPage($items, $data['pagination']['nextToken'] ?? null, ! empty($data['pagination']['nextToken']));
    }

    private function orders(MarketplaceConnection $connection, string $marketplaceId, ?DateTimeInterface $from, ?DateTimeInterface $to, ?string $cursor): MarketplaceReadPage
    {
        $query = ['marketplaceIds' => $marketplaceId, 'maxResultsPerPage' => 100, 'includedData' => 'PROCEEDS'];
        if ($from) {
            $query['createdAfter'] = $from->format(DATE_ATOM);
            if ($to) {
                $query['createdBefore'] = $to->format(DATE_ATOM);
            }
        } else {
            $query['lastUpdatedAfter'] = now()->subDays(7)->toIso8601String();
            if ($to) {
                $query['lastUpdatedBefore'] = $to->format(DATE_ATOM);
            }
        }
        if ($cursor) {
            $query['paginationToken'] = $cursor;
        }

        // Do not request BUYER or RECIPIENT datasets. The staging service also strips PII recursively.
        $data = $this->get($connection, '/orders/2026-01-01/orders', $query);
        $items = [];
        foreach (($data['orders'] ?? []) as $order) {
            if (! is_array($order)) {
                continue;
            }
            $id = (string) ($order['orderId'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[] = [
                'external_id' => $id,
                'payload' => [
                    'order_id' => $id,
                    'created_at' => $order['createdTime'] ?? null,
                    'updated_at' => $order['lastUpdatedTime'] ?? null,
                    'marketplace_id' => data_get($order, 'salesChannel.marketplaceId'),
                    'marketplace_name' => data_get($order, 'salesChannel.marketplaceName'),
                    'fulfillment_status' => data_get($order, 'fulfillment.fulfillmentStatus'),
                    'fulfilled_by' => data_get($order, 'fulfillment.fulfilledBy'),
                    'items' => array_map(fn (array $line) => [
                        'order_item_id' => $line['orderItemId'] ?? null,
                        'sku' => data_get($line, 'product.sellerSku'),
                        'asin' => data_get($line, 'product.asin'),
                        'title' => data_get($line, 'product.title'),
                        'quantity' => $line['quantityOrdered'] ?? 0,
                        'unit_price' => $this->orderItemUnitPrice($line),
                        'currency' => $this->orderItemCurrency($line),
                    ], array_filter($order['orderItems'] ?? [], 'is_array')),
                ],
            ];
        }

        return new MarketplaceReadPage($items, $data['pagination']['nextToken'] ?? null, ! empty($data['pagination']['nextToken']));
    }

    /** Prefer the retail unit price; fall back to the ITEM proceeds subtotal divided by ordered quantity. */
    private function orderItemUnitPrice(array $line): mixed
    {
        $unitPrice = data_get($line, 'product.price.unitPrice.amount');
        if (is_numeric($unitPrice)) {
            return $unitPrice;
        }

        $quantity = max(1, (int) ($line['quantityOrdered'] ?? 1));
        foreach (($line['proceeds']['breakdowns'] ?? []) as $breakdown) {
            if (is_array($breakdown) && strtoupper((string) ($breakdown['type'] ?? '')) === 'ITEM') {
                $amount = data_get($breakdown, 'subtotal.amount');
                if (is_numeric($amount)) {
                    return number_format((float) $amount / $quantity, 2, '.', '');
                }
            }
        }

        return null;
    }

    private function orderItemCurrency(array $line): ?string
    {
        $currency = data_get($line, 'product.price.unitPrice.currencyCode');
        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }
        foreach (($line['proceeds']['breakdowns'] ?? []) as $breakdown) {
            if (is_array($breakdown) && strtoupper((string) ($breakdown['type'] ?? '')) === 'ITEM') {
                $currency = data_get($breakdown, 'subtotal.currencyCode');
                if (is_string($currency) && $currency !== '') {
                    return strtoupper($currency);
                }
            }
        }

        return null;
    }

    private function inventory(MarketplaceConnection $connection, string $marketplaceId, ?string $cursor): MarketplaceReadPage
    {
        $query = [
            'granularityType' => 'Marketplace',
            'granularityId' => $marketplaceId,
            'marketplaceIds' => $marketplaceId,
            'details' => 'true',
        ];
        if ($cursor) {
            $query['nextToken'] = $cursor;
        }

        $data = $this->get($connection, '/fba/inventory/v1/summaries', $query);
        $items = [];
        foreach (($data['payload']['inventorySummaries'] ?? $data['inventorySummaries'] ?? []) as $summary) {
            if (! is_array($summary)) {
                continue;
            }
            $sku = (string) ($summary['sellerSku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $details = $summary['inventoryDetails'] ?? [];
            $items[] = [
                'external_id' => $sku.':FBA',
                'payload' => [
                    'marketplace_sku' => $sku,
                    'fulfillment_channel' => 'FBA',
                    'available_qty' => (int) ($details['fulfillableQuantity'] ?? 0),
                    'reserved_qty' => (int) data_get($details, 'reservedQuantity.totalReservedQuantity', 0),
                    'inbound_qty' => (int) ($details['inboundWorkingQuantity'] ?? 0)
                        + (int) ($details['inboundShippedQuantity'] ?? 0)
                        + (int) ($details['inboundReceivingQuantity'] ?? 0),
                    'asin' => $summary['asin'] ?? null,
                    'condition' => $summary['condition'] ?? null,
                ],
            ];
        }

        $next = $data['payload']['pagination']['nextToken'] ?? $data['pagination']['nextToken'] ?? null;

        return new MarketplaceReadPage($items, is_string($next) ? $next : null, is_string($next) && $next !== '');
    }

    private function get(MarketplaceConnection $connection, string $path, array $query): array
    {
        $region = strtolower((string) (($connection->credentials ?? [])['region'] ?? 'eu'));
        $host = match ($region) {
            'na' => 'sellingpartnerapi-na.amazon.com',
            'fe' => 'sellingpartnerapi-fe.amazon.com',
            default => 'sellingpartnerapi-eu.amazon.com',
        };
        $token = $this->tokens->accessTokenForConnection($connection);
        $response = Http::baseUrl('https://'.$host)
            ->withHeaders([
                'x-amz-access-token' => $token,
                'x-amz-date' => gmdate('Ymd\THis\Z'),
                'user-agent' => 'SellerAutomation/1.0 (Language=PHP)',
            ])
            ->acceptJson()->connectTimeout(8)->timeout(30)->get($path, $query);

        if (! $response->successful()) {
            throw new RuntimeException('Amazon SP-API read request failed (HTTP '.$response->status().'). Check the seller authorization, operation role and marketplace region.');
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Amazon SP-API returned an invalid JSON response.');
        }

        return $json;
    }
}
