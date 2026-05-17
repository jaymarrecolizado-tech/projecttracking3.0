<?php
namespace App\Observers;
use App\Models\Site;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
class SiteObserver
{
    public function created(Site $site): void
    {
        $this->log('created', $site, null, $site->toArray());
    }
    public function updated(Site $site): void
    {
        if ($site->isDirty()) {
            $this->log('updated', $site, $site->getOriginal(), $site->getChanges());
        }
    }
    public function deleted(Site $site): void
    {
        $this->log('deleted', $site, $site->toArray(), null);
    }
    protected function log(string $action, Site $site, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => Site::class,
            'auditable_id' => $site->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
