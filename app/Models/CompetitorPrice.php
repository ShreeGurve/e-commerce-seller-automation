<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CompetitorPrice extends Model
{
    protected $fillable = ['product_id', 'marketplace_id', 'competitor_name', 'competitor_price', 'our_price', 'captured_on', 'source', 'url', 'created_by'];
    protected $casts = ['competitor_price' => 'decimal:2', 'our_price' => 'decimal:2', 'captured_on' => 'date'];
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
}
