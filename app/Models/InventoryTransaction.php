<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    const UPDATED_AT = null;

    /** avail/damaged = multiplier on quantity ('signed' = quantity carries its own sign). Transfer/Claim added with later phases. */
    public const TYPES = [
        'PURCHASE'   => ['label' => 'Purchase',        'avail' => 1,        'damaged' => 0],
        'STOCK_IN'   => ['label' => 'Stock in',        'avail' => 1,        'damaged' => 0],
        'ORDER'      => ['label' => 'Order (sale)',    'avail' => -1,       'damaged' => 0],
        'RETURN'     => ['label' => 'Customer return', 'avail' => 1,        'damaged' => 0],
        'DAMAGE'     => ['label' => 'Damage',          'avail' => -1,       'damaged' => 1],
        'LOST'       => ['label' => 'Lost',            'avail' => -1,       'damaged' => 0],
        'DISPOSAL'   => ['label' => 'Disposal',        'avail' => 0,        'damaged' => -1],
        'ORDER_CANCEL'   => ['label' => 'Order cancelled (restock)', 'avail' => 1, 'damaged' => 0],
        'RETURN_DAMAGED' => ['label' => 'Returned (damaged/used)', 'avail' => 0, 'damaged' => 1],
        'ADJUSTMENT' => ['label' => 'Adjustment (+/-)', 'avail' => 'signed', 'damaged' => 0],
    ];
    public const MANUAL = ['STOCK_IN', 'DAMAGE', 'LOST', 'DISPOSAL', 'ADJUSTMENT'];

    protected $fillable = ['product_id', 'type', 'qty_change', 'damaged_change', 'balance_after', 'unit_cost', 'reference_type', 'reference_id', 'note', 'created_by'];
    protected $casts = ['unit_cost' => 'decimal:2', 'created_at' => 'datetime'];
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
    public function reference() { return $this->morphTo(); }
}
