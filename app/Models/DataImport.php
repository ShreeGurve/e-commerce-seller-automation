<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DataImport extends Model
{
    /** field => [label, required] */
    /** metrics shared by every advertising report */
    public const ADS_METRICS = [
        'impressions' => ['Impressions / views', false], 'clicks' => ['Clicks', false], 'spend' => ['Spend (₹)', true],
        'sales' => ['Sales from ads (₹)', false], 'orders' => ['Orders from ads', false], 'units' => ['Units from ads', false],
    ];
    public const TYPES = [
        'ORDERS' => ['label' => 'Orders', 'fields' => [
            'external_order_id' => ['Order ID', true], 'order_date' => ['Order date', true], 'sku' => ['SKU', true],
            'quantity' => ['Quantity', true], 'unit_price' => ['Item price', true], 'status' => ['Order status', false],
            'external_line_id' => ['Order item ID', false], 'discount' => ['Discount', false], 'shipping' => ['Shipping charged', false],
            'ship_state' => ['Ship-to state', false], 'fulfillment' => ['Fulfilment channel', false], 'customer_ref' => ['Customer / order ref', false]]],
        'RETURNS' => ['label' => 'Returns', 'fields' => [
            'external_return_id' => ['Return ID', true], 'external_order_id' => ['Order ID', true], 'return_date' => ['Return date', true],
            'sku' => ['SKU', true], 'quantity' => ['Quantity returned', true], 'reason' => ['Return reason', false]]],
        'SETTLEMENTS' => ['label' => 'Settlements (payouts)', 'fields' => [
            'external_settlement_id' => ['Settlement ID', true], 'posted_date' => ['Posted / settlement date', true],
            'external_order_id' => ['Order ID', false], 'sku' => ['SKU', false],
            'txn_type' => ['Transaction type (long format)', false], 'amount_type' => ['Amount type (long format)', false],
            'amount_desc' => ['Amount description (long format)', false], 'amount' => ['Amount, signed (long format)', false],
            'sale_amount' => ['Sale amount (one column per item)', false], 'shipping_credit' => ['Shipping charged to customer', false],
            'refund_amount' => ['Refund amount', false], 'referral_fee' => ['Commission / referral fee', false], 'closing_fee' => ['Fixed / closing fee', false],
            'shipping_fee' => ['Shipping / fulfilment fee', false], 'collection_fee' => ['Collection / COD fee', false], 'payment_fee' => ['Payment / gateway fee', false],
            'other_fee' => ['Other fees', false], 'fee_tax' => ['GST on fees', false], 'reimbursement' => ['Reimbursement / SAFE-T credit', false], 'adjustment' => ['Adjustment, signed', false]]],
        'ADS_CAMPAIGN' => ['label' => 'Ads: campaign report', 'fields' => ['date' => ['Date', true], 'campaign' => ['Campaign name', true], ...self::ADS_METRICS]],
        'ADS_KEYWORD' => ['label' => 'Ads: keyword / targeting report', 'fields' => ['date' => ['Date', true], 'campaign' => ['Campaign name', true], 'ad_group' => ['Ad group', false],
            'keyword' => ['Keyword / targeting', true], 'match_type' => ['Match type', false], ...self::ADS_METRICS]],
        'ADS_TERM' => ['label' => 'Ads: search term report', 'fields' => ['date' => ['Date', true], 'campaign' => ['Campaign name', true], 'ad_group' => ['Ad group', false],
            'keyword' => ['Keyword / targeting that triggered it', false], 'match_type' => ['Match type', false], 'search_term' => ['Customer search term', true], ...self::ADS_METRICS]],
        'ADS_PRODUCT' => ['label' => 'Ads: advertised product report', 'fields' => ['date' => ['Date', true], 'campaign' => ['Campaign name', true], 'ad_group' => ['Ad group', false],
            'sku' => ['Advertised SKU', true], ...self::ADS_METRICS]],
        'MAPPINGS' => ['label' => 'SKU mappings', 'fields' => [
            'marketplace_sku' => ['Marketplace SKU', true], 'product_sku' => ['Our product SKU', true],
            'external_product_id' => ['ASIN / FSN', false], 'fulfillment_sku' => ['FNSKU', false], 'listing_status' => ['Listing status', false]]],
    ];
    public const DATE_FORMATS = ['auto' => 'Auto (2026-10-05, 05-10-2026, ISO)', 'd/m/Y' => 'DD/MM/YYYY', 'd-m-Y' => 'DD-MM-YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd M Y' => 'DD Mon YYYY'];

    protected $fillable = ['marketplace_id', 'data_type', 'file_name', 'stored_path', 'file_hash', 'file_size', 'status', 'options', 'mapping',
        'total_rows', 'ok_rows', 'failed_rows', 'duplicate_rows', 'error_message', 'uploaded_by', 'imported_at'];
    protected $casts = ['options' => 'array', 'mapping' => 'array', 'imported_at' => 'datetime'];

    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function rows() { return $this->hasMany(ImportRow::class, 'import_id'); }
    public function fields(): array { return self::TYPES[$this->data_type]['fields']; }
    public function typeLabel(): string { return self::TYPES[$this->data_type]['label'] ?? $this->data_type; }
}
