<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFulfillmentEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id', 'event_type', 'description', 'meta', 'user_id', 'created_at'];
    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
    public function user() { return $this->belongsTo(User::class); }
}
