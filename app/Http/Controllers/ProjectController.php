<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::withCount('sites')->get();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(Project $project): Response
    {
        $project->load(['sites' => fn ($q) => $q->with('latestDailyStatus'), 'milestones']);

        return Inertia::render('Projects/Show', ['project' => $project]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Project::create($request->validated());

        return redirect()->route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project);
    }
}
