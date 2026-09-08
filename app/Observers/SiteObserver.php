<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Site;
use App\Support\CoverageCache;
use Illuminate\Support\Facades\Request;

class SiteObserver
{
    public function saving(Site $site): void
    {
        if (blank($site->region) && isset(Site::REGIONS_BY_PROVINCE[$site->province])) {
            $site->region = Site::REGIONS_BY_PROVINCE[$site->province];
        }

        // The (project_id, ap_site_code) unique index cannot dedupe NULLs, so
        // a missing code gets a deterministic synthetic one: re-importing the
        // same source row resolves to the same code instead of stacking
        // duplicates (Plan_revision §Phase 1.5). Genuine distinct sites that
        // share every attribute get a suffix rather than a constraint crash.
        if (blank($site->ap_site_code)) {
            $code = $site->syntheticCode();
            $suffix = 1;
            while (Site::where('project_id', $site->project_id)->where('ap_site_code', $code)->exists()) {
                $code = $site->syntheticCode().'-'.(++$suffix);
            }
            $site->ap_site_code = $code;
        }
    }

    public function created(Site $site): void
    {
        $this->log('created', $site, null, $site->toArray());
        $this->flushCoverageCache();
    }

    public function updated(Site $site): void
    {
        if ($site->isDirty()) {
            $this->log('updated', $site, $site->getOriginal(), $site->getChanges());
        }
        $this->flushCoverageCache();
    }

    public function deleted(Site $site): void
    {
        $this->log('deleted', $site, $site->toArray(), null);
        $this->flushCoverageCache();
    }

    /** Cached coverage aggregates are stale the moment a site row changes. */
    private function flushCoverageCache(): void
    {
        CoverageCache::invalidate();
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
