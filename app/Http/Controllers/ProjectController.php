<?php
namespace App\Http\Controllers;
use App\Models\Project;
use Illuminate\Http\Request;
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
        $project->load(['sites' => fn($q) => $q->with('latestDailyStatus'), 'milestones']);
        return Inertia::render('Projects/Show', ['project' => $project]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:projects',
            'name' => 'required|string|max:150',
            'report_type' => 'required|in:freewifi,milestone',
            'marker_color' => 'required|string|max:7',
            'marker_shape' => 'required|in:circle,square,diamond,hexagon,star',
            'marker_icon' => 'required|string|max:50',
        ]);
        Project::create($validated);
        return redirect()->route('projects.index');
    }
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);
        $project->update($validated);
        return redirect()->route('projects.show', $project);
    }
}
