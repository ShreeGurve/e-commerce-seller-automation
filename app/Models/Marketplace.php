<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Marketplace extends Model
{
    protected $fillable = ['code', 'name', 'adapter_class', 'config', 'is_active', 'sort_order'];
    protected $casts = ['config' => 'array', 'is_active' => 'boolean'];
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }
}
