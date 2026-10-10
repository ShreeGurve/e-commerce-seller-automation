<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdvertisingGroup extends Model
{
    protected $fillable = ['campaign_id', 'name'];
    public function campaign() { return $this->belongsTo(AdvertisingCampaign::class, 'campaign_id'); }
}
