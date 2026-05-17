<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class User extends Authenticatable
{
    use Notifiable;
    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime'];
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class, 'role_user')->withPivot('project_id'); }
    public function projects(): BelongsToMany { return $this->belongsToMany(Project::class, 'role_user'); }
    public function hasPermission(string $permission, ?int $projectId = null): bool {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permission)) {
                if ($projectId === null) return true;
                $pivot = $this->roles()->where('role_id', $role->id)->first()->pivot;
                if ($pivot->project_id === null || $pivot->project_id === $projectId) return true;
            }
        }
        return false;
    }
    public function hasRole(string $roleName): bool {
        return $this->roles->contains('name', $roleName);
    }
}
