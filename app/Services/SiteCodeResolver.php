<?php

namespace App\Services;

use App\Models\Site;

/**
 * Resolves an AP site code to its canonical Site row.
 *
 * This exists because two independent ingest paths — the heartbeat API and the
 * public survey — must agree on what a site code means. `sites:dedupe` merges
 * duplicate rows and stamps `metadata.merged_into` on the soft-deleted loser
 * before hiding it, so a stale code must follow that pointer to the survivor.
 *
 * Extracted from HeartbeatController (which had this logic inline) so the two
 * paths cannot drift: a divergence here silently loses surveys or heartbeats
 * for every site that was ever merged.
 */
class SiteCodeResolver
{
    /**
     * Find the canonical site for an AP site code, following a merged
     * duplicate's `metadata.merged_into` pointer when the code belongs to a
     * soft-deleted row.
     */
    public function resolve(string $siteCode): ?Site
    {
        $site = Site::where('ap_site_code', $siteCode)->first();

        if ($site !== null) {
            return $site;
        }

        // The code may belong to a duplicate that sites:dedupe merged away.
        $mergedInto = Site::withTrashed()
            ->where('ap_site_code', $siteCode)
            ->get()
            ->map(fn (Site $trashed) => data_get($trashed->metadata, 'merged_into'))
            ->filter()
            ->first();

        return $mergedInto === null ? null : Site::find($mergedInto);
    }
}
