<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ReportController extends Controller
{
    public function index()
    {
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name']);
        return Inertia::render('Reports/Index', ['projects' => $projects]);
    }
    public function projectPdf(Request $request, Project $project, ReportingService $reportingService)
    {
        $pdf = $reportingService->generateProjectSummaryPdf($project);
        return $pdf->download("project-{$project->code}-summary.pdf");
    }
    public function provincePdf(Request $request, ReportingService $reportingService)
    {
        $request->validate(['province' => 'required|string', 'project_id' => 'nullable|exists:projects,id']);
        $pdf = $reportingService->generateProvinceReport($request->province, $request->project_id);
        return $pdf->download("province-{$request->province}-summary.pdf");
    }
}
