<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFulfillment extends Model
{
    protected $fillable = [
        'order_id', 'package_reference', 'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'carrier', 'service_level', 'tracking_number', 'tracking_url', 'invoice_number',
        'label_path', 'invoice_path', 'pickup_reference', 'pickup_scheduled_at', 'picked_up_at',
        'packing_notes', 'packed_by', 'packed_at', 'dispatched_by',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3', 'length_cm' => 'decimal:2', 'width_cm' => 'decimal:2', 'height_cm' => 'decimal:2',
        'pickup_scheduled_at' => 'datetime', 'picked_up_at' => 'datetime', 'packed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function events() { return $this->hasMany(OrderFulfillmentEvent::class, 'order_id', 'order_id')->latest('id'); }
}
