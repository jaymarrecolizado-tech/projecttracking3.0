<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['per_page' => 'nullable|integer|min:1|max:200']);

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

        return response()->json($query->paginate($validated['per_page'] ?? 50));
    }

    public function show(Site $site, Request $request): JsonResponse
    {
        $scope = $request->user()->accessibleProjectIds('sites.view');
        abort_if($scope !== null && ! in_array($site->project_id, $scope, true), 403);

        // Cap the history window — the full dailyStatuses series can be
        // thousands of rows per site.
        $site->load(['project', 'dailyStatuses' => fn ($q) => $q->latest('date')->limit(90)]);

        return response()->json($site);
    }
}
