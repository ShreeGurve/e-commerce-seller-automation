<?php

namespace App\Marketplaces\Flipkart;

use App\Marketplaces\Api\FlipkartAccessTokenProvider;
use App\Marketplaces\Sync\MarketplaceReadPage;
use App\Marketplaces\Sync\MarketplaceReadProvider;
use App\Models\MarketplaceConnection;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only Flipkart Seller API adapter. No listing/order write methods are used. */
class FlipkartReadProvider implements MarketplaceReadProvider
{
    public function __construct(private readonly FlipkartAccessTokenProvider $tokens) {}

    public function fetchPage(
        MarketplaceConnection $connection,
        string $resource,
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        ?string $cursor
    ): MarketplaceReadPage {
        $connection->loadMissing('marketplace');
        if ($connection->marketplace?->code !== 'flipkart' || $connection->status !== 'connected') {
            throw new RuntimeException('A connected Flipkart seller account is required.');
        }
        if ($resource === 'orders') {
            throw new RuntimeException('Flipkart order ingestion is not enabled in this package. The shipment/order filter contract and permissions must be validated separately before enabling it.');
        }
        if (!in_array($resource, ['products', 'inventory'], true)) {
            throw new RuntimeException('Unsupported Flipkart read resource: '.$resource);
        }

        $token = $this->tokens->accessToken($connection);
        $baseUrl = rtrim((string) config('marketplaces.flipkart.base_url', 'https://api.flipkart.net'), '/');
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('Flipkart Seller API base URL must use HTTPS.');
        }

        $body = ['filters' => [], 'page_id' => $cursor];
        $response = Http::baseUrl($baseUrl)
            ->withToken($token)->acceptJson()->asJson()
            ->connectTimeout(8)->timeout(30)
            ->post('/sellers/listings/v3/search', $body);

        if (!$response->successful()) {
            throw new RuntimeException('Flipkart listing search failed (HTTP '.$response->status().'). Check Seller API listing permissions.');
        }
        $json = $response->json();
        if (!is_array($json)) throw new RuntimeException('Flipkart returned an invalid listing search response.');

        $listings = $json['listings'] ?? [];
        if (!is_array($listings)) $listings = [];
        if (!array_is_list($listings) && $listings !== []) $listings = [$listings];
        $skuIds = array_values(array_unique(array_filter(array_map(
            static fn (array $listing) => (string) ($listing['sku_id'] ?? ''),
            array_filter($listings, 'is_array')
        ))));

        $detailsBySku = [];
        foreach (array_chunk($skuIds, 10) as $batch) {
            $detailResponse = Http::baseUrl($baseUrl)
                ->withToken($token)->acceptJson()->asJson()
                ->connectTimeout(8)->timeout(30)
                ->post('/sellers/listings/v3/details', ['sku_ids' => $batch]);
            if (!$detailResponse->successful()) {
                throw new RuntimeException('Flipkart listing detail read failed (HTTP '.$detailResponse->status().').');
            }
            $available = $detailResponse->json('available', []);
            if (is_array($available)) $detailsBySku = array_merge($detailsBySku, $available);
        }

        $items = [];
        foreach ($listings as $listing) {
            if (!is_array($listing)) continue;
            $sku = (string) ($listing['sku_id'] ?? '');
            if ($sku === '') continue;
            $detail = $detailsBySku[$sku] ?? [];
            if (!is_array($detail)) $detail = [];

            if ($resource === 'products') {
                $items[] = [
                    'external_id' => $sku,
                    'payload' => [
                        'marketplace_sku' => $sku,
                        'listing_id' => $detail['listing_id'] ?? $listing['listing_id'] ?? null,
                        'product_id' => $detail['product_id'] ?? $listing['product_id'] ?? null,
                        'product_title' => $detail['product_title'] ?? null,
                        'listing_status' => $detail['listing_status'] ?? null,
                        'selling_price' => data_get($detail, 'price.selling_price', data_get($detail, 'price.flipkart_selling_price')),
                        'mrp' => data_get($detail, 'price.mrp'),
                        'currency' => data_get($detail, 'price.currency', 'INR'),
                        'fulfillment_profile' => $detail['fulfillment_profile'] ?? null,
                    ],
                ];
            } else {
                $locations = is_array($detail['locations'] ?? null) ? $detail['locations'] : [];
                $availableQty = 0;
                $pendingQty = 0;
                foreach ($locations as $location) {
                    if (!is_array($location)) continue;
                    if (($location['status'] ?? 'ENABLED') === 'ENABLED') $availableQty += (int) ($location['inventory'] ?? 0);
                    $pendingQty += (int) ($location['pending_inventory'] ?? 0);
                }
                $items[] = [
                    'external_id' => $sku.':FLIPKART',
                    'payload' => [
                        'marketplace_sku' => $sku,
                        'fulfillment_channel' => $detail['fulfillment_profile'] ?? 'FLIPKART',
                        'available_qty' => $availableQty,
                        'reserved_qty' => $pendingQty,
                        'product_id' => $detail['product_id'] ?? $listing['product_id'] ?? null,
                        'listing_status' => $detail['listing_status'] ?? null,
                    ],
                ];
            }
        }

        $next = $json['next_page_id'] ?? null;
        $hasMore = (bool) ($json['has_more'] ?? false);
        return new MarketplaceReadPage($items, is_string($next) ? $next : null, $hasMore);
    }
}
