<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReturn extends Model
{
    use SoftDeletes;
    protected $table = 'returns';
    public const TYPES = [
        'CHANGED_MIND' => 'Customer changed mind', 'WRONG_PRODUCT' => 'Wrong product', 'DAMAGED' => 'Damaged', 'USED' => 'Used',
        'MISSING_PART' => 'Missing part', 'EMPTY_PACKAGE' => 'Empty package', 'DIFFERENT_PRODUCT' => 'Different product',
        'SELLER_FAULT' => 'Seller fault', 'COURIER_DAMAGE' => 'Courier damage', 'UNKNOWN' => 'Unknown',
    ];
    protected $fillable = ['order_id', 'marketplace_id', 'return_number', 'external_return_id', 'return_date', 'return_type', 'reason_text',
        'status', 'inspected_at', 'claim_eligible', 'notes', 'created_by'];
    protected $casts = ['return_date' => 'date', 'inspected_at' => 'datetime', 'claim_eligible' => 'boolean'];
    public function order() { return $this->belongsTo(Order::class); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function items() { return $this->hasMany(ReturnItem::class, 'return_id'); }
    public function media() { return $this->hasMany(ReturnMedia::class, 'return_id'); }
    public function refunds() { return $this->hasMany(Refund::class, 'return_id'); }
    public function claims() { return $this->hasMany(Claim::class, 'return_id'); }
}
