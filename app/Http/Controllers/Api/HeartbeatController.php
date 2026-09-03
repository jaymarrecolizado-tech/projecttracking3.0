<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceMetric;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Device heartbeat ingest — see docs/FREEWIFI_MONITORING_PLAN.md §Phase 2.
 * Field probes POST { site_code, status, ... } with a Sanctum token; today's
 * SiteDailyStatus row is upserted so NOC views stay live. The extended
 * telemetry fields (uptime, cpu, latency, throughput, power) land in
 * device_metrics as a high-frequency time-series alongside the daily row.
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
            'device_serial' => 'nullable|string|exists:devices,serial_number',
            'uptime_s' => 'nullable|integer|min:0',
            'cpu_pct' => 'nullable|numeric|between:0,100',
            'mem_pct' => 'nullable|numeric|between:0,100',
            'wan_latency_ms' => 'nullable|integer|min:0',
            'bw_rx_mbps' => 'nullable|numeric|min:0',
            'bw_tx_mbps' => 'nullable|numeric|min:0',
            'power' => 'nullable|array',
            'power.source' => 'nullable|string|max:30',
            'power.battery_v' => 'nullable|numeric|min:0',
            'power.solar_w' => 'nullable|numeric|min:0',
            'firmware' => 'nullable|string|max:100',
        ]);

        // A merged duplicate's AP code resolves to its canonical site
        // (sites:dedupe stamps metadata.merged_into before soft-deleting).
        $site = Site::where('ap_site_code', $validated['site_code'])->first()
            ?? Site::withTrashed()->where('ap_site_code', $validated['site_code'])
                ->get()
                ->map(fn ($trashed) => Site::find(data_get($trashed->metadata, 'merged_into')))
                ->filter()
                ->first();

        abort_if($site === null, 404, 'Unknown site code.');

        // Approved-and-locked records are authoritative — probes must not overwrite them.
        $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', today())->first();
        if ($existing && $existing->entry_status === 'LOCKED') {
            return response()->json([
                'ok' => false,
                'error' => 'locked',
                'message' => "Today's record for site {$site->ap_site_code} is locked.",
            ], 409);
        }

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

            $this->recordMetric($site, $validated);

            // A returning beat closes any "heartbeat lost" episode (docs §4.3).
            SiteStatusEvent::where('site_id', $site->id)
                ->whereNull('resolved_at')
                ->where('cause', 'heartbeat_lost')
                ->update(['resolved_at' => now()]);

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

    private function recordMetric(Site $site, array $v): void
    {
        $device = isset($v['device_serial'])
            ? Device::where('serial_number', $v['device_serial'])->first(['id', 'firmware_version'])
            : null;

        $metricOnly = array_diff_key($v, array_flip(['site_code', 'status', 'bandwidth_mbps', 'users']));

        // Only persist a series point when the probe actually sent telemetry
        // beyond the daily UP/DOWN pair — plain probes shouldn't grow the table.
        if ($device === null && $metricOnly === []) {
            return;
        }

        DeviceMetric::create([
            'device_id' => $device?->id,
            'site_id' => $site->id,
            'ts' => now(),
            'uptime_s' => $v['uptime_s'] ?? null,
            'cpu_pct' => $v['cpu_pct'] ?? null,
            'mem_pct' => $v['mem_pct'] ?? null,
            'latency_ms' => $v['wan_latency_ms'] ?? null,
            'clients' => $v['users'] ?? null,
            'rx_mbps' => $v['bw_rx_mbps'] ?? null,
            'tx_mbps' => $v['bw_tx_mbps'] ?? null,
            'battery_v' => $v['power']['battery_v'] ?? null,
            'solar_w' => $v['power']['solar_w'] ?? null,
            'power_source' => $v['power']['source'] ?? null,
            'firmware' => $v['firmware'] ?? $device?->firmware_version,
            'raw' => $metricOnly === [] ? null : $metricOnly,
        ]);
    }
}
