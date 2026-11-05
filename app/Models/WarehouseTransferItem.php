<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseTransferItem extends Model {
 protected $fillable=['warehouse_transfer_id','sku','item_name','quantity','unit'];
 protected $casts=['quantity'=>'decimal:3'];
 public function transfer(){return $this->belongsTo(WarehouseTransfer::class,'warehouse_transfer_id');}
}
