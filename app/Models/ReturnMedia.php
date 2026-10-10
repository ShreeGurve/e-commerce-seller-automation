<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReturnMedia extends Model
{
    protected $table = 'return_media';
    protected $fillable = ['return_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];
    public function isImage(): bool { return str_starts_with($this->mime, 'image/'); }
}
