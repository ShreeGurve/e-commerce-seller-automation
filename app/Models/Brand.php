<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;
    protected $fillable = ['business_id', 'name', 'is_active'];
    public function business() { return $this->belongsTo(Business::class); }
    public function products() { return $this->hasMany(Product::class); }
}
