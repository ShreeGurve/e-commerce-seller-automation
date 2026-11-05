<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStockCountItem extends Model {
 protected $fillable=['warehouse_stock_count_id','sku','item_name','system_quantity','counted_quantity','variance_quantity','unit','notes'];
 protected $casts=['system_quantity'=>'decimal:3','counted_quantity'=>'decimal:3','variance_quantity'=>'decimal:3'];
}
