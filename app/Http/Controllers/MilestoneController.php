<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Models\Project;

class MilestoneController extends Controller
{
    public function index(Project $project)
    {
        return response()->json($project->milestones);
    }

    public function store(StoreMilestoneRequest $request, Project $project)
    {
        $milestone = $project->milestones()->create($request->validated());

        return response()->json($milestone, 201);
    }
}
