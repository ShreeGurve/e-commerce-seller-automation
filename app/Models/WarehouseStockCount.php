<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStockCount extends Model {
 protected $fillable=['count_number','warehouse_location_id','count_date','status','notes','created_by','finalized_by','finalized_at'];
 protected $casts=['count_date'=>'date','finalized_at'=>'datetime'];
 public function items(){return $this->hasMany(WarehouseStockCountItem::class);}
 public function location(){return $this->belongsTo(WarehouseLocation::class,'warehouse_location_id');}
}
