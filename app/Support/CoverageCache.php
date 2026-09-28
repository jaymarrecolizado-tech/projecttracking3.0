<?php

namespace App\Support;

use Closure;
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

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $filters
     * @param  callable(): TValue  $compute
     * @return TValue
     */
    public static function remember(string $kind, array $filters, callable $compute): mixed
    {
        $version = (int) Cache::get('coverage.version', 0);

        // Closure::fromCallable is what lets the cache infer the stored type —
        // a bare `callable` leaves it guessing, and a wrong guess here would be
        // a coverage figure the caller believes.
        return Cache::remember(
            "coverage.{$kind}.v{$version}.".md5(serialize($filters)),
            now()->addMinutes(self::TTL_MINUTES),
            Closure::fromCallable($compute),
        );
    }

    public static function invalidate(): void
    {
        Cache::increment('coverage.version');
    }
}
