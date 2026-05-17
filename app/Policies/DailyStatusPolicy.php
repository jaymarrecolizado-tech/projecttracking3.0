<?php
namespace App\Policies;
use App\Models\User;
use App\Models\SiteDailyStatus;
class DailyStatusPolicy
{
    public function viewAny(User $user, ?int $projectId = null): bool
    {
        return $user->hasPermission('daily.view', $projectId);
    }
    public function view(User $user, SiteDailyStatus $status): bool
    {
        return $user->hasPermission('daily.view', $status->site->project_id);
    }
    public function create(User $user, ?int $projectId = null): bool
    {
        return $user->hasPermission('daily.create', $projectId);
    }
    public function update(User $user, SiteDailyStatus $status): bool
    {
        if ($status->entry_status === 'LOCKED') return false;
        if ($status->entry_status === 'APPROVED') return $user->hasPermission('daily.approve');
        return $user->hasPermission('daily.edit', $status->site->project_id);
    }
    public function submit(User $user, SiteDailyStatus $status): bool
    {
        return $user->hasPermission('daily.submit', $status->site->project_id);
    }
    public function approve(User $user, SiteDailyStatus $status): bool
    {
        return $user->hasPermission('daily.approve', $status->site->project_id);
    }
}
