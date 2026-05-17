<?php
namespace App\Policies;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SitePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user) { return $user->hasPermission('sites.view'); }
    public function view(User $user, Site $site) { return $user->hasPermission('sites.view', $site->project_id); }
    public function create(User $user) { return $user->hasPermission('sites.create'); }
    public function update(User $user, Site $site) { return $user->hasPermission('sites.edit', $site->project_id); }
    public function delete(User $user, Site $site) { return $user->hasPermission('sites.delete', $site->project_id); }
}
