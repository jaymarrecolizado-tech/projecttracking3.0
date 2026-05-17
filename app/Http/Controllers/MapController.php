<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\GeoJsonService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class MapController extends Controller
{
    public function index()
    {
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name', 'marker_color', 'marker_shape', 'marker_icon']);
        return Inertia::render('Map/Index', ['projects' => $projects]);
    }
    public function geojson(Request $request, GeoJsonService $geoJsonService)
    {
        $filters = $request->only(['project_id', 'status', 'region', 'province', 'municipality', 'island_group']);
        return response()->json($geoJsonService->getSitesForMap($filters));
    }
}
