<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    public const STATUSES = ['IMPORTED', 'CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'RETURNED', 'REFUNDED', 'CLOSED', 'CANCELLED'];
    public const PIPELINE = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'RETURNED', 'REFUNDED', 'CLOSED'];
    public const TRANSITIONS = [
        'IMPORTED' => ['CONFIRMED', 'CANCELLED'], 'CONFIRMED' => ['PACKED', 'CANCELLED'], 'PACKED' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED', 'RETURNED'], 'DELIVERED' => ['RETURNED', 'CLOSED'], 'RETURNED' => ['REFUNDED', 'CLOSED'],
        'REFUNDED' => ['CLOSED'], 'CANCELLED' => [], 'CLOSED' => [],
    ];
    public const FULFILLMENT = ['SELF' => 'Self ship', 'MARKETPLACE' => 'Marketplace fulfilled (FBA / Flipkart Fulfilled)'];
    public const BADGES = ['IMPORTED' => 'secondary', 'CONFIRMED' => 'info', 'PACKED' => 'primary', 'SHIPPED' => 'primary', 'DELIVERED' => 'success',
        'RETURNED' => 'warning', 'REFUNDED' => 'warning', 'CLOSED' => 'dark', 'CANCELLED' => 'danger'];

    protected $fillable = ['marketplace_id', 'external_order_id', 'order_number', 'order_date', 'status', 'marketplace_status', 'fulfillment_type',
        'customer_ref', 'ship_state', 'subtotal', 'shipping_charged', 'tax_amount', 'total_amount', 'source', 'import_id',
        'shipped_at', 'delivered_at', 'cancelled_at', 'notes', 'created_by', 'recon_status'];
    protected $casts = ['order_date' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2', 'shipping_charged' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function history() { return $this->hasMany(OrderStatusHistory::class)->latest('id'); }
    public function returns() { return $this->hasMany(ProductReturn::class); }
    public function refunds() { return $this->hasMany(Refund::class); }
    public function claims() { return $this->hasMany(Claim::class); }
    public function profits() { return $this->hasMany(OrderProfit::class); }
    public function settlementItems() { return $this->hasMany(SettlementItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function nextStatuses(): array { return self::TRANSITIONS[$this->status] ?? []; }
}
