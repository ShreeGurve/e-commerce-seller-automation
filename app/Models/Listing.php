<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Listing extends Model {
 protected $fillable=['product_id','marketplace_id','external_listing_id','title','bullet_points','description','backend_keywords','special_features','has_a_plus_content','image_urls','status','score','score_breakdown'];
 protected $casts=['bullet_points'=>'array','special_features'=>'array','image_urls'=>'array','score_breakdown'=>'array','has_a_plus_content'=>'boolean'];
 public function product(){return $this->belongsTo(Product::class);} public function marketplace(){return $this->belongsTo(Marketplace::class);} public function versions(){return $this->hasMany(ListingVersion::class);}
 public function calculateScore(): array { $checks=['title'=>[$this->title,20,80],'bullets'=>[implode(' ', $this->bullet_points??[]),20,120],'description'=>[$this->description,15,300],'keywords'=>[$this->backend_keywords,15,30],'images'=>[count($this->image_urls??[]),15,5],'a_plus'=>[$this->has_a_plus_content,15,true]]; $total=0;$break=[]; foreach($checks as $key=>[$value,$weight,$target]) { $ok=is_bool($target)?(bool)$value:(is_numeric($target)&&is_numeric($value)?min(1,(float)$value/$target):(mb_strlen(trim((string)$value))>0?min(1,mb_strlen(trim((string)$value))/$target):0)); $points=(int)round($weight*$ok);$break[$key]=['score'=>$points,'max'=>$weight];$total+=$points;} return ['score'=>$total,'breakdown'=>$break]; }
}
