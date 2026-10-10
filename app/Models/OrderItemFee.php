<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItemFee extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['order_item_id', 'fee_type', 'amount', 'tax_amount', 'source', 'rule_id'];
    protected $casts = ['amount' => 'decimal:2', 'tax_amount' => 'decimal:2'];
}
