<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['business_id', 'name', 'email', 'phone', 'password', 'is_active', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];

    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles() { return $this->belongsToMany(Role::class); }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains('slug', 'super-admin');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = Permission::whereHas('roles.users', fn ($q) => $q->where('users.id', $this->id))
                ->pluck('name')->all();
        }
        return in_array($permission, $this->permissionCache, true);
    }
}
