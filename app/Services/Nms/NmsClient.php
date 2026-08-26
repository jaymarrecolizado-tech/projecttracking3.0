<?php

namespace App\Services\Nms;

use Illuminate\Support\Collection;

/**
 * Contract for pulling live site statuses from a Network/CMS management system.
 *
 * Implement this (e.g. SnmpNmsClient, RestNmsClient) and bind it in
 * AppServiceProvider, then `php artisan nms:pull` ingests through the same
 * upsert path as the heartbeat API — no schema changes needed.
 */
interface NmsClient
{
    /**
     * Fetch the current status for the given AP site codes.
     *
     * @param  array<int, string>  $siteCodes
     * @return Collection<int, array{site_code: string, status: string, bandwidth_mbps: float|null, users: int|null}>
     *                                                                                                                Status must be one of: UP, DOWN, DOWN_SERVER, NO_NMS.
     */
    public function currentStatuses(array $siteCodes): Collection;

    /** Whether a real client is configured (credentials present). */
    public function isConfigured(): bool;
}
