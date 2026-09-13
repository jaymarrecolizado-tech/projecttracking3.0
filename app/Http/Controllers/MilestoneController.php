<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class MilestoneController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json($project->milestones);
    }

    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        $milestone = $project->milestones()->create($request->validated());

        return response()->json($milestone, 201);
    }
}
