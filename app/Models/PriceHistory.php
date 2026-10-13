<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $table = 'price_history';
    const UPDATED_AT = null;
    protected $fillable = ['product_id', 'marketplace_id', 'price', 'previous_price', 'reason', 'source', 'effective_on', 'changed_by'];
    protected $casts = ['price' => 'decimal:2', 'previous_price' => 'decimal:2', 'effective_on' => 'date'];
    public function user() { return $this->belongsTo(User::class, 'changed_by'); }
}
