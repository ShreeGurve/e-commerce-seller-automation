<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'draft', 'discontinued'];

    protected $fillable = [
        'brand_id', 'category_id', 'sku', 'parent_sku', 'name', 'subcategory', 'product_type', 'hsn', 'gst_rate',
        'mrp', 'default_selling_price', 'min_selling_price', 'target_selling_price',
        'weight_g', 'length_cm', 'width_cm', 'height_cm', 'status', 'image_path', 'description', 'notes', 'created_by',
    ];

    public function brand() { return $this->belongsTo(Brand::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function marketplaces() { return $this->hasMany(ProductMarketplace::class); }
    public function costs() { return $this->hasMany(ProductCostHistory::class); }
    public function currentCosts() { return $this->hasMany(ProductCostHistory::class)->whereNull('effective_to'); }

    /** Current amount for a cost type (eager-load currentCosts on lists to avoid N+1). */
    public function currentCost(string $type): string
    {
        $row = $this->relationLoaded('currentCosts')
            ? $this->currentCosts->firstWhere('cost_type', $type)
            : $this->currentCosts()->where('cost_type', $type)->first();
        return $row ? (string) $row->amount : '0.00';
    }

    public function totalUnitCost(): string
    {
        $costs = $this->relationLoaded('currentCosts') ? $this->currentCosts : $this->currentCosts()->get();
        return number_format((float) $costs->sum('amount'), 2, '.', '');
    }
}
