<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GeoJsonService;
class MapApiController extends Controller
{
    public function sites(GeoJsonService $geoJsonService)
    {
        return response()->json($geoJsonService->getSitesForMap());
    }
    public function projectSites(Project $project, GeoJsonService $geoJsonService)
    {
        return response()->json($geoJsonService->getSitesForProject($project));
    }
}
