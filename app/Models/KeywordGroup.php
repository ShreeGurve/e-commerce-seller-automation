<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KeywordGroup extends Model
{
    protected $fillable = ['name', 'description'];
    public function keywords() { return $this->hasMany(Keyword::class, 'group_id'); }
}
