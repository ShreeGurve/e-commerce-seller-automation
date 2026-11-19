<?php

namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonReadProvider;
use App\Marketplaces\Api\AmazonLwaTokenProvider;
use App\Marketplaces\Api\FlipkartAccessTokenProvider;
use App\Marketplaces\Flipkart\FlipkartReadProvider;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class MarketplaceReadProviderTest extends TestCase
{
    public function test_amazon_products_use_lwa_header_and_search_listings_read_endpoint(): void
    {
        config(['marketplaces.amazon.enabled' => true, 'marketplaces.amazon.api_calls_enabled' => true]);
        Http::fake([
            'https://sellingpartnerapi-eu.amazon.com/listings/2021-08-01/items/SELLER-123*' => Http::response([
                'items' => [[
                    'sku' => 'SKU-100',
                    'summaries' => [['asin' => 'B000000001', 'itemName' => 'Test product', 'status' => ['BUYABLE']]],
                    'fulfillmentAvailability' => [['fulfillmentChannelCode' => 'DEFAULT', 'quantity' => 4]],
                ]],
                'pagination' => ['nextToken' => 'NEXT-1'],
            ], 200),
        ]);

        $tokens = Mockery::mock(AmazonLwaTokenProvider::class);
        $tokens->shouldReceive('accessTokenForConnection')->once()->andReturn('lwa-test-token');
        $provider = new AmazonReadProvider($tokens);
        $page = $provider->fetchPage($this->connection('amazon', [
            'marketplace_id' => 'A1F83G8C2ARO7P', 'region' => 'eu', 'seller_id' => 'SELLER-123',
        ]), 'products', null, null, null);

        $this->assertSame('SKU-100', $page->items[0]['external_id']);
        $this->assertSame('B000000001', $page->items[0]['payload']['asin']);
        $this->assertSame('NEXT-1', $page->nextCursor);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/listings/2021-08-01/items/SELLER-123')
            && $request->hasHeader('x-amz-access-token', 'lwa-test-token')
            && $request->method() === 'GET');
    }

    public function test_amazon_orders_use_current_search_orders_api_without_buyer_or_recipient_datasets(): void
    {
        config(['marketplaces.amazon.enabled' => true, 'marketplaces.amazon.api_calls_enabled' => true]);
        Http::fake([
            'https://sellingpartnerapi-na.amazon.com/orders/2026-01-01/orders*' => Http::response([
                'orders' => [[
                    'orderId' => 'ORDER-1', 'createdTime' => '2026-11-01T10:00:00Z',
                    'salesChannel' => ['marketplaceId' => 'ATVPDKIKX0DER'],
                    'fulfillment' => ['fulfillmentStatus' => 'UNSHIPPED'],
                    'orderItems' => [[
                        'orderItemId' => 'LINE-1', 'quantityOrdered' => 2,
                        'product' => ['sellerSku' => 'SKU-1', 'asin' => 'B0001', 'title' => 'Example', 'price' => ['unitPrice' => ['amount' => '10.00', 'currencyCode' => 'USD']]],
                    ], [
                        'orderItemId' => 'LINE-2', 'quantityOrdered' => 2,
                        'product' => ['sellerSku' => 'SKU-2', 'asin' => 'B0002', 'title' => 'Proceeds fallback'],
                        'proceeds' => ['breakdowns' => [['type' => 'ITEM', 'subtotal' => ['amount' => '40.00', 'currencyCode' => 'INR']]]],
                    ]],
                ]],
                'pagination' => ['nextToken' => 'ORDER-NEXT'],
            ], 200),
        ]);

        $tokens = Mockery::mock(AmazonLwaTokenProvider::class);
        $tokens->shouldReceive('accessTokenForConnection')->once()->andReturn('lwa-token');
        $provider = new AmazonReadProvider($tokens);
        $page = $provider->fetchPage($this->connection('amazon', [
            'marketplace_id' => 'ATVPDKIKX0DER', 'region' => 'na', 'seller_id' => 'SELLER-NA',
        ]), 'orders', new \DateTimeImmutable('2026-11-01T00:00:00Z'), null, null);

        $this->assertSame('ORDER-1', $page->items[0]['external_id']);
        $this->assertSame('SKU-1', $page->items[0]['payload']['items'][0]['sku']);
        $this->assertSame('10.00', $page->items[0]['payload']['items'][0]['unit_price']);
        $this->assertSame('20.00', $page->items[0]['payload']['items'][1]['unit_price']);
        $this->assertSame('INR', $page->items[0]['payload']['items'][1]['currency']);
        $this->assertSame('ORDER-NEXT', $page->nextCursor);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/orders/2026-01-01/orders')
            && str_contains($request->url(), 'includedData=PROCEEDS')
            && $request->method() === 'GET');
    }

    public function test_amazon_fba_inventory_maps_fulfillable_reserved_and_inbound_quantities(): void
    {
        config(['marketplaces.amazon.enabled' => true, 'marketplaces.amazon.api_calls_enabled' => true]);
        Http::fake([
            'https://sellingpartnerapi-eu.amazon.com/fba/inventory/v1/summaries*' => Http::response([
                'payload' => ['inventorySummaries' => [[
                    'sellerSku' => 'SKU-FBA', 'asin' => 'B0002', 'condition' => 'New',
                    'inventoryDetails' => [
                        'fulfillableQuantity' => 8,
                        'reservedQuantity' => ['totalReservedQuantity' => 3],
                        'inboundWorkingQuantity' => 1,
                        'inboundShippedQuantity' => 2,
                        'inboundReceivingQuantity' => 1,
                    ],
                ]], 'pagination' => ['nextToken' => 'INV-NEXT']],
            ], 200),
        ]);
        $tokens = Mockery::mock(AmazonLwaTokenProvider::class);
        $tokens->shouldReceive('accessTokenForConnection')->once()->andReturn('lwa-token');
        $page = (new AmazonReadProvider($tokens))->fetchPage($this->connection('amazon', [
            'marketplace_id' => 'A1F83G8C2ARO7P', 'region' => 'eu', 'seller_id' => 'SELLER-EU',
        ]), 'inventory', null, null, null);

        $this->assertSame(8, $page->items[0]['payload']['available_qty']);
        $this->assertSame(3, $page->items[0]['payload']['reserved_qty']);
        $this->assertSame(4, $page->items[0]['payload']['inbound_qty']);
        $this->assertSame('INV-NEXT', $page->nextCursor);
    }

    public function test_flipkart_products_and_inventory_use_read_only_listing_search_and_details(): void
    {
        config(['marketplaces.flipkart.enabled' => true, 'marketplaces.flipkart.api_calls_enabled' => true, 'marketplaces.flipkart.base_url' => 'https://api.flipkart.net']);
        Http::fake([
            'https://api.flipkart.net/sellers/listings/v3/search' => Http::response([
                'listings' => [['sku_id' => 'FK-SKU-1', 'listing_id' => 'LIST-1', 'product_id' => 'FSN-1']],
                'has_more' => false,
            ], 200),
            'https://api.flipkart.net/sellers/listings/v3/details' => Http::response([
                'available' => ['FK-SKU-1' => [
                    'listing_id' => 'LIST-1', 'product_id' => 'FSN-1', 'product_title' => 'Flipkart product',
                    'listing_status' => 'ACTIVE', 'price' => ['selling_price' => 499, 'mrp' => 599, 'currency' => 'INR'],
                    'fulfillment_profile' => 'NON_FBF',
                    'locations' => [
                        ['status' => 'ENABLED', 'inventory' => 7, 'pending_inventory' => 2],
                        ['status' => 'DISABLED', 'inventory' => 100, 'pending_inventory' => 0],
                    ],
                ]],
            ], 200),
        ]);
        $tokens = Mockery::mock(FlipkartAccessTokenProvider::class);
        $tokens->shouldReceive('accessToken')->twice()->andReturn('fk-token');
        $provider = new FlipkartReadProvider($tokens);
        $connection = $this->connection('flipkart', ['app_id' => 'app', 'app_secret' => 'secret']);

        $products = $provider->fetchPage($connection, 'products', null, null, null);
        $inventory = $provider->fetchPage($connection, 'inventory', null, null, null);

        $this->assertSame('Flipkart product', $products->items[0]['payload']['product_title']);
        $this->assertSame(7, $inventory->items[0]['payload']['available_qty']);
        $this->assertSame(2, $inventory->items[0]['payload']['reserved_qty']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sellers/listings/v3/search') && $request->method() === 'POST');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sellers/listings/v3/details') && $request->method() === 'POST');
    }

    public function test_flipkart_orders_fail_closed_until_the_order_filter_contract_is_enabled(): void
    {
        $tokens = Mockery::mock(FlipkartAccessTokenProvider::class);
        $provider = new FlipkartReadProvider($tokens);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('order ingestion is not enabled');
        $provider->fetchPage($this->connection('flipkart', ['app_id' => 'app', 'app_secret' => 'secret']), 'orders', null, null, null);
    }

    private function connection(string $code, array $credentials): MarketplaceConnection
    {
        $marketplace = new Marketplace(['code' => $code, 'name' => ucfirst($code)]);
        $connection = new MarketplaceConnection([
            'status' => 'connected', 'environment' => 'production', 'credentials' => $credentials,
        ]);
        $connection->exists = true;
        $connection->setRelation('marketplace', $marketplace);
        return $connection;
    }
}
