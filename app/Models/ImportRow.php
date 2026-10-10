<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['import_id', 'row_number', 'status', 'message', 'raw', 'entity_type', 'entity_id'];
    protected $casts = ['raw' => 'array'];
}
