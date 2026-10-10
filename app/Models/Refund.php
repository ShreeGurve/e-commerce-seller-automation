<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use SoftDeletes;
    protected $fillable = ['order_id', 'return_id', 'external_refund_id', 'amount', 'refund_date', 'reason', 'notes', 'created_by'];
    protected $casts = ['amount' => 'decimal:2', 'refund_date' => 'date'];
    public function order() { return $this->belongsTo(Order::class); }
    public function productReturn() { return $this->belongsTo(ProductReturn::class, 'return_id'); }
}
