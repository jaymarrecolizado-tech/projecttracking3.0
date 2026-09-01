<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];

    // two_factor_secret never leaves the backend (also shared via Inertia auth.user).
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime'];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('project_id');
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'role_user');
    }

    public function hasPermission(string $permission, ?int $projectId = null): bool
    {
        foreach ($this->roles as $role) {
            if (! $role->permissions->contains('name', $permission)) {
                continue;
            }
            if ($projectId === null) {
                return true;
            }
            // Pivot already loaded with roles — no extra query per check.
            $pivotProjectId = data_get($role, 'pivot.project_id');
            if ($pivotProjectId === null || (int) $pivotProjectId === (int) $projectId) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled_at !== null && $this->two_factor_secret !== null;
    }
}
