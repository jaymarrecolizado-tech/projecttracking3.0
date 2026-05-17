<?php
namespace App\Policies;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user) { return true; } // Everyone can see projects list, though typically viewers can too
    public function view(User $user, Project $project) { return true; }
    public function create(User $user) { return $user->hasPermission('projects.manage'); }
    public function update(User $user, Project $project) { return $user->hasPermission('projects.manage'); }
    public function delete(User $user, Project $project) { return $user->hasPermission('projects.manage'); }
}
