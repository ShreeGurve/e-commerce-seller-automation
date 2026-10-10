<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductCostHistory extends Model
{
    protected $table = 'product_cost_history';
    public const TYPES = [
        'COGS' => 'COGS', 'PACKAGING' => 'Packaging', 'LABEL' => 'Label',
        'TRANSPORT' => 'Transportation', 'OTHER' => 'Other',
    ];
    protected $fillable = ['product_id', 'cost_type', 'amount', 'effective_from', 'effective_to', 'note', 'created_by'];
    protected $casts = ['amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function product() { return $this->belongsTo(Product::class); }
}
