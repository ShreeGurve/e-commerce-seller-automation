<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStockBalance extends Model {
 protected $fillable=['warehouse_location_id','sku','item_name','quantity','unit'];
 protected $casts=['quantity'=>'decimal:3'];
 public function location(){return $this->belongsTo(WarehouseLocation::class,'warehouse_location_id');}
}
