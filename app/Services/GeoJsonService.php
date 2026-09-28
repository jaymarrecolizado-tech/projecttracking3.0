<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * GeoJSON payloads for the map. Every feature is built from an explicit
 * column list, so the property shapes below are the contract the Vue map
 * reads — a key renamed here is a silently missing pin in the browser.
 *
 * @phpstan-type Filters array{project_id?: mixed, project_scope?: list<int>|null, status?: mixed, region?: mixed, province?: mixed, district?: mixed, municipality?: mixed, barangay?: mixed, island_group?: mixed, site_type?: mixed}
 * @phpstan-type Feature array{type: 'Feature', geometry: array{type: 'Point', coordinates: array{float, float}}, properties: array<string, mixed>}
 * @phpstan-type FeatureCollection array{type: 'FeatureCollection', features: list<Feature>, truncated?: bool}
 */
class GeoJsonService
{
    /** Hard ceiling per response so an unfiltered fleet can't OOM the worker. */
    private const MAX_FEATURES = 10000;

    /** @return FeatureCollection */
    public function getSitesForProject(Project $project): array
    {
        $sites = $project->sites()->with('latestDailyStatus')->get();

        return $this->buildFeatureCollection($sites);
    }

    /**
     * @param  Filters  $filters
     * @return FeatureCollection
     */
    public function getSitesForMap(array $filters = []): array
    {
        // Marker payloads are filter-deterministic, so a short cache survives
        // filter-ping-pong without recomputing the fleet on every pan.
        return Cache::remember(
            'map.sites.v1.'.md5(serialize($filters)),
            now()->addMinutes(5),
            fn () => $this->buildSitesForMap($filters),
        );
    }

    /**
     * @param  Filters  $filters
     * @return FeatureCollection
     */
    private function buildSitesForMap(array $filters): array
    {
        // Hydrate only the project fields the marker payload uses — the
        // full model (logo blob metadata, timestamps) is dead weight per row.
        // latestDailyStatus stays unprojected: a column list on a latestOfMany
        // relation compiles an ambiguous-column join on SQLite.
        $query = Site::query()->with([
            'project:id,code,name,marker_color,marker_shape,marker_icon',
            'latestDailyStatus',
        ]);
        $this->applyGeoFilters($query, $filters);
        $query->whereNotNull(['latitude', 'longitude']);

        // Fetch one past the ceiling to detect truncation without a COUNT(*).
        $sites = $query->limit(self::MAX_FEATURES + 1)->get();
        $truncated = $sites->count() > self::MAX_FEATURES;
        $collection = $this->buildFeatureCollection($sites->take(self::MAX_FEATURES));
        $collection['truncated'] = $truncated;

        return $collection;
    }

    /**
     * Deployed-device markers (Plan §Map 4.3): one point per SITE hosting at
     * least one deployed unit — multiple units at one location aggregate into
     * a single marker carrying the device roster. Health color comes from the
     * site's daily status so ops read it the same way as the site layer.
     *
     * @param  Filters  $filters
     * @return FeatureCollection
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

    /** @return Feature  */
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

    /** @return Feature  */
    public function getSiteGeoJson(Site $site): array
    {
        return $this->buildFeature($site);
    }

    /**
     * Geo/project filters shared by the site and device layers.
     *
     * @param  Builder<Site>  $query
     * @param  Filters  $filters
     */
    private function applyGeoFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('project_scope', $filters)) {
            // Null scope = unrestricted; otherwise confine markers to the
            // caller's assigned projects (empty scope matches nothing).
            if ($filters['project_scope'] === null) {
                unset($filters['project_scope']);
            } elseif ($filters['project_scope'] === []) {
                $query->whereRaw('1 = 0');

                return;
            } else {
                $query->where('sites.project_id', $filters['project_scope']);
                unset($filters['project_scope']);
            }
        }        if (! empty($filters['project_id'])) {
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

    /**
     * @param  Collection<int, Site>  $sites
     * @return FeatureCollection
     */
    protected function buildFeatureCollection(Collection $sites): array
    {
        $features = $sites->map(fn ($site) => $this->buildFeature($site))->values()->all();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /** @return Feature  */
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
