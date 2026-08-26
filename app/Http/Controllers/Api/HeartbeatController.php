<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Device heartbeat ingest — see docs/FREEWIFI_MONITORING_PLAN.md §Phase 2.
 * Field probes POST { site_code, status, bandwidth?, users? } with a Sanctum
 * token; today's SiteDailyStatus row is upserted so NOC views stay live.
 */
class HeartbeatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_code' => 'required|string|exists:sites,ap_site_code',
            'status' => 'required|in:UP,DOWN',
            'bandwidth_mbps' => 'nullable|numeric|min:0',
            'users' => 'nullable|integer|min:0',
        ]);

        $site = Site::where('ap_site_code', $validated['site_code'])->firstOrFail();

        $status = DB::transaction(function () use ($site, $validated) {
            $attributes = [
                'status' => $validated['status'],
                'bandwidth_utilization_mbps' => $validated['bandwidth_mbps'] ?? null,
                'total_unique_users' => $validated['users'] ?? null,
            ];
            // whereDate avoids the Eloquent date-cast vs Y-m-d storage mismatch
            // that makes updateOrCreate() double-insert on some drivers.
            $status = SiteDailyStatus::where('site_id', $site->id)
                ->whereDate('date', today())
                ->first();
            if ($status) {
                $status->fill($attributes)->save();
            } else {
                $status = SiteDailyStatus::create($attributes + ['site_id' => $site->id, 'date' => today()->toDateString()]);
            }

            // A heartbeat implies the deployment is live.
            if ($site->status === 'planned') {
                $site->update(['status' => 'active']);
            }

            return $status;
        });

        return response()->json([
            'ok' => true,
            'site_id' => $site->id,
            'date' => $status->date->toDateString(),
            'status' => $status->status,
        ]);
    }
}
