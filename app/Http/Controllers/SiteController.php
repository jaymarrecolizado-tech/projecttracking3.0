<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Project;
use App\Models\Site;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function index()
    {
        $sites = Site::with('project:id,code,name,marker_color')->latest()->paginate(20);

        return Inertia::render('Sites/Index', ['sites' => $sites]);
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

    public function byProject(Project $project)
    {
        $sites = $project->sites()->with('latestDailyStatus')->paginate(20);

        return Inertia::render('Sites/Index', ['sites' => $sites, 'project' => $project]);
    }
}
