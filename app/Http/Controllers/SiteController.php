<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $sites = Site::query()
            ->with(['project:id,code,name,marker_color', 'latestDailyStatus'])
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('location_name', 'like', "%{$v}%")
                ->orWhere('ap_site_code', 'like', "%{$v}%")
                ->orWhere('municipality', 'like', "%{$v}%")
                ->orWhere('barangay', 'like', "%{$v}%")
                ->orWhere('nationwide_id', 'like', "%{$v}%")))
            ->when($request->input('project_id'), fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('province'), fn ($q, $v) => $q->where('province', $v))
            ->when($request->input('today') === 'down', fn ($q) => $q->whereHas('latestDailyStatus', fn ($s) => $s->whereIn('status', ['DOWN', 'DOWN_SERVER'])))
            ->orderBy('location_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'filters' => $this->filterPayload($request),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'provinces' => Site::whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
        ]);
    }

    public function show(Site $site)
    {
        $site->load(['project', 'latestDailyStatus', 'dailyStatuses' => fn ($q) => $q->latest('date')->take(30),
            'activeDeployments.device.deviceModel:id,manufacturer,model_name,model_number']);

        return Inertia::render('Sites/Show', ['site' => $site]);
    }

    public function store(StoreSiteRequest $request)
    {
        $site = Site::create($request->validated() + [
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('sites.show', $site);
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $site->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('sites.show', $site);
    }

    public function destroy(Site $site)
    {
        $site->delete();

        return redirect()->route('sites.index');
    }

    public function byProject(Request $request, Project $project)
    {
        $sites = $project->sites()
            ->with(['latestDailyStatus'])
            ->orderBy('location_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'project' => $project,
            'filters' => $this->filterPayload($request),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'provinces' => Site::whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
        ]);
    }

    private function filterPayload(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'project_id' => $request->input('project_id'),
            'status' => $request->input('status'),
            'province' => $request->input('province'),
            'today' => $request->input('today'),
        ];
    }
}
