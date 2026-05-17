<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\Request;
class MilestoneController extends Controller
{
    public function index(Project $project)
    {
        return response()->json($project->milestones);
    }
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'milestone_name' => 'required|string|max:200',
            'milestone_order' => 'required|integer|min:0',
            'weight_pct' => 'required|numeric|between:0,100',
            'description' => 'nullable|string',
        ]);
        $milestone = $project->milestones()->create($validated);
        return response()->json($milestone, 201);
    }
}
