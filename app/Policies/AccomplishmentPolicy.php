<?php
namespace App\Policies;
use App\Models\User;
use App\Models\SiteAccomplishment;
class AccomplishmentPolicy
{
    public function viewAny(User $user, ?int $projectId = null): bool
    {
        return $user->hasPermission('accomplishment.view', $projectId);
    }
    public function view(User $user, SiteAccomplishment $accomplishment): bool
    {
        return $user->hasPermission('accomplishment.view', $accomplishment->site->project_id);
    }
    public function create(User $user, ?int $projectId = null): bool
    {
        return $user->hasPermission('accomplishment.create', $projectId);
    }
    public function update(User $user, SiteAccomplishment $accomplishment): bool
    {
        return $user->hasPermission('accomplishment.edit', $accomplishment->site->project_id);
    }
}
