<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClaimDocument extends Model
{
    protected $fillable = ['claim_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];
    public function isImage(): bool { return str_starts_with($this->mime, 'image/'); }
}
