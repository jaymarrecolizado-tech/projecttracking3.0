<?php

namespace App\Policies;

use App\Models\SiteAccomplishment;
use App\Models\User;

class SiteAccomplishmentPolicy
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
