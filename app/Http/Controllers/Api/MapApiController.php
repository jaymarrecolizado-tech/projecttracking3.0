<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GeoJsonService;
use Illuminate\Http\Request;

class MapApiController extends Controller
{
    public function sites(Request $request, GeoJsonService $geoJsonService)
    {
        return response()->json($geoJsonService->getSitesForMap([
            'project_scope' => $request->user()->accessibleProjectIds('sites.view'),
        ]));
    }

    public function projectSites(Project $project, Request $request, GeoJsonService $geoJsonService)
    {
        $scope = $request->user()->accessibleProjectIds('sites.view');
        abort_if($scope !== null && ! in_array($project->id, $scope, true), 403);

        return response()->json($geoJsonService->getSitesForProject($project));
    }
}
