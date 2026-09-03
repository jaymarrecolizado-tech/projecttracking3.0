<?php

namespace App\Services;

use App\Models\DeviceDeployment;
use App\Models\Project;
use App\Models\Site;

class GeoJsonService
{
    public function getSitesForProject(Project $project): array
    {
        $sites = $project->sites()->with('latestDailyStatus')->get();

        return $this->buildFeatureCollection($sites);
    }

    public function getSitesForMap(array $filters = []): array
    {
        $query = Site::query()->with(['project', 'latestDailyStatus']);
        $this->applyGeoFilters($query, $filters);
        $query->whereNotNull(['latitude', 'longitude']);

        return $this->buildFeatureCollection($query->get());
    }

    /**
     * Deployed-device markers (Plan §Map 4.3): one point per SITE hosting at
     * least one deployed unit — multiple units at one location aggregate into
     * a single marker carrying the device roster. Health color comes from the
     * site's daily status so ops read it the same way as the site layer.
     */
    public function getDeployedDevicesForMap(array $filters = []): array
    {
        $sites = Site::query()
            ->whereHas('activeDeployments.device', fn ($q) => $q->where('status', 'deployed'))
            ->where(function ($q) use ($filters) {
                $this->applyGeoFilters($q, $filters);
                $q->whereNotNull(['sites.latitude', 'sites.longitude']);
            })
            ->with([
                'project:id,code,name',
                'latestDailyStatus',
                'activeDeployments.device:id,asset_tag,serial_number,device_model_id',
                'activeDeployments.device.deviceModel:id,manufacturer,model_name',
            ])
            ->get();

        $features = $sites->map(fn ($site) => $this->buildSiteDevicesFeature($site))->values()->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function buildSiteDevicesFeature(Site $site): array
    {
        $units = $site->activeDeployments
            ->filter(fn ($deployment) => $deployment->device !== null)
            ->map(fn ($deployment) => [
                'device_id' => $deployment->device->id,
                'asset_tag' => $deployment->device->asset_tag,
                'model' => trim(($deployment->device->deviceModel->manufacturer ?? '').' '.($deployment->device->deviceModel->model_name ?? '')),
            ])
            ->values();

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $site->longitude, (float) $site->latitude],
            ],
            'properties' => [
                'site_id' => $site->id,
                'location_name' => $site->location_name,
                'ap_site_code' => $site->ap_site_code,
                'site_type' => $site->site_type,
                'barangay' => $site->barangay,
                'municipality' => $site->municipality,
                'province' => $site->province,
                'district' => $site->district,
                'status' => $site->status,
                // Site health drives the marker color for ops.
                'daily_status' => data_get($site->latestDailyStatus, 'status') ?? 'NO_DATA',
                'project_name' => $site->project?->name,
                'device_count' => $units->count(),
                'devices' => $units->all(),
            ],
        ];
    }

    public function getSiteGeoJson(Site $site): array
    {
        return $this->buildFeature($site);
    }

    /** Geo/project filters shared by the site and device layers. */
    private function applyGeoFilters($query, array $filters): void
    {
        if (! empty($filters['project_id'])) {
            $query->where('sites.project_id', $filters['project_id']);
        }
        foreach (['status', 'region', 'province', 'district', 'municipality', 'barangay', 'island_group'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('sites.'.$column, $filters[$column]);
            }
        }
        if (! empty($filters['site_type'])) {
            $query->where('sites.site_type', $filters['site_type']);
        }
    }

    protected function buildFeatureCollection($sites): array
    {
        $features = $sites->map(fn ($site) => $this->buildFeature($site))->values()->all();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    protected function buildFeature(Site $site): array
    {
        // latestOfMany relations resolve to null at runtime even though the
        // static type says otherwise — data_get keeps both PHPStan and reality happy.
        $latestStatus = data_get($site->latestDailyStatus, 'status') ?? 'NO_DATA';
        $bandwidth = data_get($site->latestDailyStatus, 'bandwidth_utilization_mbps');
        $users = data_get($site->latestDailyStatus, 'total_unique_users');

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $site->longitude, (float) $site->latitude],
            ],
            'properties' => [
                'id' => $site->id,
                'project_id' => $site->project_id,
                'project_code' => $site->project->code ?? null,
                'project_name' => $site->project->name ?? null,
                'marker_color' => $site->project->marker_color ?? '#64748b',
                'marker_shape' => $site->project->marker_shape ?? 'circle',
                'marker_icon' => $site->project->marker_icon ?? null,
                'location_name' => $site->location_name,
                'ap_site_code' => $site->ap_site_code,
                'barangay' => $site->barangay,
                'municipality' => $site->municipality,
                'province' => $site->province,
                'district' => $site->district,
                'region' => $site->region,
                'status' => $site->status,
                'daily_status' => $latestStatus,
                'bandwidth' => $bandwidth,
                'users' => $users,
                'date_of_activation' => $site->date_of_activation?->format('Y-m-d'),
                'site_type' => $site->site_type,
                'last_mile_tech' => $site->last_mile_tech,
                'isp_provider' => $site->isp_provider,
            ],
        ];
    }
}
