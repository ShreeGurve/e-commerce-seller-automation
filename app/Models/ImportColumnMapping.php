<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ImportColumnMapping extends Model
{
    protected $fillable = ['marketplace_id', 'data_type', 'mapping', 'updated_by'];
    protected $casts = ['mapping' => 'array'];
}
