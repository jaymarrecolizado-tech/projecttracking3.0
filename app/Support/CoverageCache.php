<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache envelope for the two coverage aggregates (site-type + barangay).
 * They walk every site row plus deployments, so the dashboard and map should
 * not recompute them per request (Plan_revision §Phase 3.2).
 *
 * File/database cache drivers cannot flush by prefix, so entries live in a
 * versioned namespace: invalidate() bumps the version and older entries simply
 * expire by TTL. Device-deployment writes do not invalidate — deployments only
 * ever flip a site into "deployed", so a stale entry under-reports for at most
 * the TTL, never over-reports.
 */
class CoverageCache
{
    private const TTL_MINUTES = 10;

    public static function remember(string $kind, array $filters, callable $compute): mixed
    {
        $version = (int) Cache::get('coverage.version', 0);

        return Cache::remember(
            "coverage.{$kind}.v{$version}.".md5(serialize($filters)),
            now()->addMinutes(self::TTL_MINUTES),
            $compute,
        );
    }

    public static function invalidate(): void
    {
        Cache::increment('coverage.version');
    }
}
