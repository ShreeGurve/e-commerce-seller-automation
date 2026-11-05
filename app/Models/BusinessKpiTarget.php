<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessKpiTarget extends Model {
    protected $fillable=['target_month','metric_key','target_value','unit','notes','created_by'];
    protected $casts=['target_month'=>'date','target_value'=>'decimal:2'];
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}
