<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnInspection extends Model
{
    protected $fillable = ['return_id', 'return_item_id', 'received_qty', 'condition', 'restock_qty', 'disposition', 'note', 'inspected_by'];

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function returnItem()
    {
        return $this->belongsTo(ReturnItem::class);
    }
}
