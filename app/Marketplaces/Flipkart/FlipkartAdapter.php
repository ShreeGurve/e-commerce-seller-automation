<?php
namespace App\Marketplaces\Flipkart;

use App\Marketplaces\BaseAdapter;

class FlipkartAdapter extends BaseAdapter
{
    protected array $aliases = [
        'ORDERS' => [
            'external_order_id' => ['order id', 'order_id', 'orderid'],
            'order_date' => ['order date', 'order approval date', 'ordered on'],
            'sku' => ['sku', 'seller sku', 'sku id'],
            'quantity' => ['quantity', 'qty'],
            'unit_price' => ['selling price', 'selling price per item', 'invoice amount', 'price'],
            'status' => ['order item status', 'item status', 'order status', 'status'],
            'external_line_id' => ['order item id', 'order_item_id'],
            'discount' => ['discount'],
            'shipping' => ['shipping charges', 'shipping fee'],
            'ship_state' => ['shipping state', 'delivery state', 'state'],
            'fulfillment' => ['fulfilment type', 'fulfillment type', 'shipment type'],
            'customer_ref' => ['order reference'],
        ],
        'RETURNS' => [
            'external_return_id' => ['return id', 'return_id'],
            'external_order_id' => ['order id', 'order_id'],
            'return_date' => ['return date', 'return requested date', 'return created date'],
            'sku' => ['sku', 'seller sku'],
            'quantity' => ['quantity', 'return quantity'],
            'reason' => ['return reason', 'reason', 'return sub reason'],
        ],
        'SETTLEMENTS' => [
            'external_settlement_id' => ['settlement ref no', 'settlement id', 'neft id', 'payment id'], 'posted_date' => ['settlement date', 'payment date', 'order item settlement date'],
            'external_order_id' => ['order id', 'order_id'], 'sku' => ['sku', 'seller sku'],
            'sale_amount' => ['settlement value', 'sale amount', 'item price'], 'shipping_credit' => ['shipping charges'], 'refund_amount' => ['refund', 'refund amount'],
            'referral_fee' => ['commission', 'commission fee', 'marketplace fee'], 'closing_fee' => ['fixed fee'], 'shipping_fee' => ['shipping fee', 'reverse shipping fee'],
            'collection_fee' => ['collection fee'], 'payment_fee' => ['payment fee'], 'fee_tax' => ['gst on fees', 'service tax', 'tax on fees'],
            'reimbursement' => ['reimbursement', 'safe-t'], 'adjustment' => ['adjustment'],
        ],
        'ADS' => [
            'date' => ['date', 'day'], 'campaign' => ['campaign name', 'campaign'], 'ad_group' => ['adgroup name', 'ad group name', 'ad group'],
            'keyword' => ['keyword', 'search keyword'], 'match_type' => ['match type'], 'search_term' => ['search term', 'query'],
            'sku' => ['sku id', 'sku', 'fsn'], 'impressions' => ['views', 'impressions'], 'clicks' => ['clicks'], 'spend' => ['ad spend', 'spend', 'estimated ad spend'],
            'sales' => ['total revenue (rs.)', 'total revenue', 'direct revenue (rs.)', 'revenue'],
            'orders' => ['orders', 'order count'], 'units' => ['total converted units', 'units sold', 'direct units sold'],
        ],
        'MAPPINGS' => [
            'marketplace_sku' => ['sku', 'seller sku'],
            'product_sku' => ['internal sku', 'product sku', 'our sku'],
            'external_product_id' => ['fsn', 'flipkart serial number'],
            'fulfillment_sku' => [],
            'listing_status' => ['listing status', 'status'],
        ],
    ];
    protected array $statusMap = [
        'pending' => 'IMPORTED', 'approved' => 'CONFIRMED', 'packed' => 'PACKED', 'readytodispatch' => 'PACKED', 'rtd' => 'PACKED',
        'shipped' => 'SHIPPED', 'dispatched' => 'SHIPPED', 'intransit' => 'SHIPPED', 'delivered' => 'DELIVERED',
        'cancelled' => 'CANCELLED', 'canceled' => 'CANCELLED', 'returnrequested' => 'DELIVERED', 'returned' => 'DELIVERED',
    ];
}
