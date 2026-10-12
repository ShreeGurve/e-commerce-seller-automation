<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeRule extends Model
{
    use SoftDeletes;
    public const TYPES = ['REFERRAL' => 'Referral / commission', 'CLOSING' => 'Closing / fixed fee', 'SHIPPING' => 'Shipping fee',
        'COLLECTION' => 'Collection fee', 'PAYMENT' => 'Payment gateway fee', 'OTHER' => 'Other fee'];
    public const APPLIES = ['PRICE' => 'Selling price (incl. GST)', 'PRICE_EX_TAX' => 'Selling price (excl. GST)'];
    protected $fillable = ['marketplace_id', 'category_id', 'fee_type', 'name', 'percentage', 'fixed_amount', 'applies_on', 'weight_min_g', 'weight_max_g',
        'effective_from', 'effective_to', 'is_active', 'created_by'];
    protected $casts = ['percentage' => 'decimal:4', 'fixed_amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'is_active' => 'boolean'];
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function category() { return $this->belongsTo(Category::class); }
}
