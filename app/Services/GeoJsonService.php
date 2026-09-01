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
     * Deployed-device markers (Plan §Map 4.3): one point per active
     * deployment, geometry from the host site, health from the site's daily
     * status so ops read color the same way as the site layer.
     */
    public function getDeployedDevicesForMap(array $filters = []): array
    {
        $deployments = DeviceDeployment::query()
            ->whereNull('removed_at')
            ->whereHas('device', fn ($q) => $q->where('status', 'deployed'))
            ->whereHas('site', function ($q) use ($filters) {
                $this->applyGeoFilters($q, $filters);
                $q->whereNotNull(['latitude', 'longitude']);
            })
            ->with([
                'device:id,serial_number,device_model_id',
                'device.deviceModel:id,manufacturer,model_name',
                'site:id,location_name,ap_site_code,barangay,municipality,province,region,site_type,status,latitude,longitude,project_id',
                'site.project:id,code,name',
                // No column subset here: latestOfMany + nested select produces
                // ambiguous site_id SQL on some drivers.
                'site.latestDailyStatus',
            ])
            ->get();

        $features = $deployments->map(fn ($deployment) => $this->buildDeviceFeature($deployment))->values()->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
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

    private function buildDeviceFeature(DeviceDeployment $deployment): array
    {
        $site = $deployment->site;
        $device = $deployment->device;

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $site->longitude, (float) $site->latitude],
            ],
            'properties' => [
                'deployment_id' => $deployment->id,
                'device_id' => $device?->id,
                'asset_tag' => $device?->asset_tag,
                'serial_number' => $device?->serial_number,
                'model' => trim(($device->deviceModel->manufacturer ?? '').' '.($device->deviceModel->model_name ?? '')),
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
            ],
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
