<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BarangayCoverageService;
use App\Services\GeoBoundaryService;
use App\Services\GeoFilterOptions;
use App\Services\GeoJsonService;
use App\Services\SiteCoverageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name', 'marker_color', 'marker_shape', 'marker_icon']);

        return Inertia::render('Map/Index', [
            'projects' => $projects,
            'siteTypes' => app(GeoFilterOptions::class)->siteTypes(),
            // Unfiltered lists for first paint so the page has no waterfall.
            'initialOptions' => app(GeoFilterOptions::class)->for(),
            // URL state so map shares work (Plan §Map 5).
            'filters' => $request->only(['project_id', 'status', 'province', 'district', 'municipality', 'barangay', 'site_type', 'deployed_only']),
        ]);
    }

    public function geojson(Request $request, GeoJsonService $geoJsonService)
    {
        $filters = $request->only(['project_id', 'status', 'region', 'province', 'district', 'municipality', 'barangay', 'site_type', 'island_group']);

        if ($request->boolean('deployed_only')) {
            return response()->json($geoJsonService->getDeployedDevicesForMap($filters));
        }

        return response()->json($geoJsonService->getSitesForMap($filters));
    }

    /** Cascade options for the geo filters (Plan §Map 4.4) — children stay
     * empty until a parent is chosen, sourced from sites that have data. */
    public function filterOptions(Request $request, GeoFilterOptions $options)
    {
        return response()->json($options->for(
            $request->only(['province', 'district', 'municipality']),
        ));
    }

    public function boundaries(Request $request, GeoBoundaryService $service)
    {
        $level = $request->input('level', 'province');
        $filters = $request->only(['province', 'district', 'municipality']);

        return response()->json($service->forLevel($level, $filters));
    }

    public function coverage(Request $request, SiteCoverageService $service)
    {
        $filters = $request->only(['project_id', 'status', 'province', 'district', 'municipality', 'barangay', 'site_type']);

        return response()->json($service->coverage($filters));
    }

    public function barangayCoverage(Request $request, BarangayCoverageService $service)
    {
        $filters = $request->only(['project_id', 'province', 'district', 'municipality']);

        return response()->json($service->coverage($filters));
    }
}
