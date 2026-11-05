<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseLocation extends Model {
 protected $fillable=['code','name','location_type','address','contact_person','contact_phone','is_active','notes'];
 protected $casts=['is_active'=>'boolean'];
 public function balances(){return $this->hasMany(WarehouseStockBalance::class);}
}
