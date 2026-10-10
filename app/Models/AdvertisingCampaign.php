<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdvertisingCampaign extends Model
{
    protected $fillable = ['marketplace_id', 'name', 'daily_budget'];
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function groups() { return $this->hasMany(AdvertisingGroup::class, 'campaign_id'); }
}
