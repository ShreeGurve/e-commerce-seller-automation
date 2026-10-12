<?php
namespace App\Marketplaces\Amazon;

use App\Marketplaces\BaseAdapter;

class AmazonAdapter extends BaseAdapter
{
    // Header names are best-effort guesses from common Seller Central reports. The mapping screen lets you fix any of them and remembers your choice.
    protected array $aliases = [
        'ORDERS' => [
            'external_order_id' => ['amazon-order-id', 'order-id', 'amazon order id'],
            'order_date' => ['purchase-date', 'order-date', 'purchase date'],
            'sku' => ['sku', 'seller-sku', 'merchant-sku'],
            'quantity' => ['quantity', 'quantity-purchased', 'qty'],
            'unit_price' => ['item-price', 'item price'],
            'status' => ['order-status', 'item-status', 'status'],
            'external_line_id' => ['order-item-id', 'amazon-order-item-id'],
            'discount' => ['item-promotion-discount', 'promotion-discount'],
            'shipping' => ['shipping-price', 'shipping price'],
            'ship_state' => ['ship-state', 'shipping-state'],
            'fulfillment' => ['fulfillment-channel', 'fulfilment-channel'],
            'customer_ref' => ['merchant-order-id'],
        ],
        'RETURNS' => [
            'external_return_id' => ['return-id', 'rma-id', 'return-request-id'],
            'external_order_id' => ['order-id', 'amazon-order-id'],
            'return_date' => ['return-date', 'return-request-date'],
            'sku' => ['merchant-sku', 'sku'],
            'quantity' => ['quantity', 'return-quantity', 'returned-quantity'],
            'reason' => ['return-reason', 'reason', 'detailed-disposition'],
        ],
        'SETTLEMENTS' => [
            'external_settlement_id' => ['settlement-id'], 'posted_date' => ['posted-date', 'posted-date-time'], 'external_order_id' => ['order-id', 'amazon-order-id'],
            'sku' => ['sku', 'merchant-sku'], 'txn_type' => ['transaction-type'], 'amount_type' => ['amount-type'],
            'amount_desc' => ['amount-description'], 'amount' => ['amount'],
        ],
        'ADS' => [
            'date' => ['date', 'start date', 'day'], 'campaign' => ['campaign name', 'campaign'], 'ad_group' => ['ad group name', 'ad group'],
            'keyword' => ['targeting', 'keyword', 'keyword text'], 'match_type' => ['match type'], 'search_term' => ['customer search term', 'search term'],
            'sku' => ['advertised sku', 'sku'], 'impressions' => ['impressions'], 'clicks' => ['clicks'], 'spend' => ['spend', 'cost'],
            'sales' => ['7 day total sales', '14 day total sales', '30 day total sales', 'total sales', 'sales'],
            'orders' => ['7 day total orders (#)', '14 day total orders (#)', '30 day total orders (#)', 'total orders', 'orders'],
            'units' => ['7 day total units (#)', '14 day total units (#)', '30 day total units (#)', 'total units', 'units'],
        ],
        'MAPPINGS' => [
            'marketplace_sku' => ['seller-sku', 'sku', 'merchant-sku'],
            'product_sku' => ['internal-sku', 'product-sku', 'our-sku'],
            'external_product_id' => ['asin', 'asin1', 'product-id'],
            'fulfillment_sku' => ['fnsku', 'fulfillment-network-sku'],
            'listing_status' => ['listing-status', 'status'],
        ],
    ];
    protected array $statusMap = [
        'pending' => 'IMPORTED', 'unshipped' => 'CONFIRMED', 'partiallyshipped' => 'PACKED', 'shipping' => 'SHIPPED', 'shipped' => 'SHIPPED',
        'delivered' => 'DELIVERED', 'canceled' => 'CANCELLED', 'cancelled' => 'CANCELLED', 'unfulfillable' => 'CANCELLED',
    ];
}
