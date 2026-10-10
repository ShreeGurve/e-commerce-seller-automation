<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = ['marketplace_id', 'external_settlement_id', 'period_start', 'period_end', 'total_amount', 'item_count', 'import_id'];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'total_amount' => 'decimal:2'];
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function items() { return $this->hasMany(SettlementItem::class); }
}
