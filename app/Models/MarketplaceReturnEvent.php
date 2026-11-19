<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceReturnEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['return_id', 'event_type', 'from_status', 'to_status', 'details', 'user_id', 'created_at'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
    public function productReturn() { return $this->belongsTo(ProductReturn::class, 'return_id'); }
}
