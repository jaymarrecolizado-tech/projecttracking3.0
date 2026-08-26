<?php

namespace App\Services;

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
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['region'])) {
            $query->where('region', $filters['region']);
        }
        if (! empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }
        if (! empty($filters['municipality'])) {
            $query->where('municipality', $filters['municipality']);
        }
        if (! empty($filters['island_group'])) {
            $query->where('island_group', $filters['island_group']);
        }

        return $this->buildFeatureCollection($query->get());
    }

    public function getSiteGeoJson(Site $site): array
    {
        return $this->buildFeature($site);
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
