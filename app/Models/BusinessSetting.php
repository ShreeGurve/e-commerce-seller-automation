<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = ['business_id', 'group', 'key', 'value', 'type'];
}
