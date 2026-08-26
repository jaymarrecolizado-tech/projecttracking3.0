<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount('sites')->get();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(Project $project)
    {
        $project->load(['sites' => fn ($q) => $q->with('latestDailyStatus'), 'milestones']);

        return Inertia::render('Projects/Show', ['project' => $project]);
    }

    public function store(StoreProjectRequest $request)
    {
        Project::create($request->validated());

        return redirect()->route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project);
    }
}
