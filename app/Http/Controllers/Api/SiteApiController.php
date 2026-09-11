<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Site::with('project:id,code,name,marker_color');
        if (($scope = $request->user()->accessibleProjectIds('sites.view')) !== null) {
            $query->whereIn('project_id', $scope);
        }
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->region) {
            $query->where('region', $request->region);
        }
        if ($request->province) {
            $query->where('province', $request->province);
        }

        return response()->json($query->paginate($request->per_page ?? 50));
    }

    public function show(Site $site, Request $request)
    {
        $scope = $request->user()->accessibleProjectIds('sites.view');
        abort_if($scope !== null && ! in_array($site->project_id, $scope, true), 403);

        $site->load('project', 'dailyStatuses');

        return response()->json($site);
    }
}
