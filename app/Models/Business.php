<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'legal_name', 'gstin', 'currency', 'timezone'];
    public function brands() { return $this->hasMany(Brand::class); }
    public function settings() { return $this->hasMany(BusinessSetting::class); }
}
