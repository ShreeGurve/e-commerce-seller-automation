<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessActivityLog extends Model {
    protected $fillable=['business_id','actor_id','action','subject_type','subject_id','details','ip_address'];
    protected $casts=['details'=>'array'];
    public function actor(){return $this->belongsTo(User::class,'actor_id');}
}
