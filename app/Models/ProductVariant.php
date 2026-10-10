<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;
    protected $fillable = ['product_id', 'sku', 'name', 'attributes', 'barcode', 'status'];
    protected $casts = ['attributes' => 'array'];
    public function product() { return $this->belongsTo(Product::class); }
}
