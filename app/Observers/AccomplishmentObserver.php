<?php
namespace App\Observers;
use App\Models\SiteAccomplishment;
use App\Models\AccomplishmentHistory;
class AccomplishmentObserver
{
    public function updated(SiteAccomplishment $accomplishment): void
    {
        $changes = [];
        if ($accomplishment->isDirty('status')) {
            $changes['old_status'] = $accomplishment->getOriginal('status');
            $changes['new_status'] = $accomplishment->status;
        }
        if ($accomplishment->isDirty('pct_complete')) {
            $changes['old_pct'] = $accomplishment->getOriginal('pct_complete');
            $changes['new_pct'] = $accomplishment->pct_complete;
        }
        if (!empty($changes)) {
            AccomplishmentHistory::create(array_merge($changes, [
                'accomplishment_id' => $accomplishment->id,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]));
        }
    }
}
