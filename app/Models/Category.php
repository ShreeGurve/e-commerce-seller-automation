<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    protected $fillable = ['parent_id', 'name', 'hsn', 'gst_rate', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function parent() { return $this->belongsTo(Category::class, 'parent_id'); }
    public function products() { return $this->hasMany(Product::class); }
}
