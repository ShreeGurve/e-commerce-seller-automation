<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseTransfer extends Model {
 protected $fillable=['transfer_number','source_location_id','destination_location_id','transfer_date','status','notes','created_by','dispatched_by','dispatched_at','received_by','received_at'];
 protected $casts=['transfer_date'=>'date','dispatched_at'=>'datetime','received_at'=>'datetime'];
 public function items(){return $this->hasMany(WarehouseTransferItem::class);}
 public function source(){return $this->belongsTo(WarehouseLocation::class,'source_location_id');}
 public function destination(){return $this->belongsTo(WarehouseLocation::class,'destination_location_id');}
}
