<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateProvinceReportRequest;
use App\Jobs\GenerateReport;
use App\Models\Project;
use App\Models\ReportExport;
use App\Services\GeoFilterOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name']);
        $exports = ReportExport::where('user_id', $request->user()->id)
            ->latest()
            ->take(10)
            ->get(['id', 'type', 'params', 'status', 'download_name', 'error', 'completed_at', 'created_at']);

        $geoOptions = app(GeoFilterOptions::class);

        return Inertia::render('Reports/Index', [
            'projects' => $projects,
            'exports' => $exports,
            'siteTypes' => $geoOptions->siteTypes(),
            'initialOptions' => $geoOptions->for(),
        ]);
    }

    public function projectPdf(Request $request, Project $project)
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'project',
            'params' => ['project_id' => $project->id],
            'download_name' => "project-{$project->code}-summary.pdf",
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function provincePdf(GenerateProvinceReportRequest $request)
    {
        $validated = $request->validated();
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'province',
            'params' => [
                'province' => $validated['province'],
                'project_id' => $validated['project_id'] ?? null,
            ],
            'download_name' => "province-{$validated['province']}-summary.pdf",
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function siteTypePdf(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'province' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
        ]);
        $filters = collect($validated)->filter()->all();
        $scope = $filters['province'] ?? 'nationwide';

        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'site_type',
            'params' => ['filters' => $filters],
            'download_name' => "site-type-coverage-{$scope}.pdf",
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function barangayCoveragePdf(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'province' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
        ]);
        $filters = collect($validated)->filter()->all();
        $scope = $filters['province'] ?? 'region-ii';

        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'barangay_coverage',
            'params' => ['filters' => $filters],
            'download_name' => "barangay-coverage-{$scope}.pdf",
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function download(Request $request, ReportExport $export)
    {
        abort_unless(
            (int) $export->user_id === (int) $request->user()->id || $request->user()->hasPermission('reports.export'),
            403,
        );
        abort_unless($export->status === 'DONE' && $export->filename && Storage::disk('local')->exists($export->filename), 404);

        return Storage::disk('local')->download($export->filename, $export->download_name ?? 'report.pdf');
    }
}
