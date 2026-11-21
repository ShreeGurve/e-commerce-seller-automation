<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicPricingRun extends Model
{
    protected $fillable = ['business_id', 'marketplace_id', 'status', 'rules_checked', 'recommendations_created', 'skipped_count', 'summary', 'started_at', 'finished_at'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];
}
