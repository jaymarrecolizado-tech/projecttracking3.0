<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateProvinceReportRequest;
use App\Jobs\GenerateReport;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ReportExport;
use App\Services\GeoFilterOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Build a safe download filename. Province/scope values are user-supplied,
     * so every part is slugified before it reaches a Content-Disposition header.
     */
    private function downloadName(array $parts): string
    {
        $slug = collect($parts)
            ->map(fn ($part) => Str::slug((string) $part))
            ->filter()
            ->implode('-');

        return ($slug === '' ? 'report' : $slug).'.pdf';
    }

    public function index(Request $request)
    {
        // marker_color is rendered as the legend dot on each project button.
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name', 'marker_color']);
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
            'download_name' => $this->downloadName(['project', $project->code, 'summary']),
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
            'download_name' => $this->downloadName(['province', $validated['province'], 'summary']),
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
            'download_name' => $this->downloadName(['site-type-coverage', $scope]),
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
            'download_name' => $this->downloadName(['barangay-coverage', $scope]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    /** Re-queue a failed export with its original filter set (§Phase 5.5). */
    public function retry(Request $request, ReportExport $export)
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);
        abort_unless($export->status === 'FAILED', 422, 'Only failed exports can be retried.');

        $export->update(['status' => 'PENDING', 'error' => null, 'completed_at' => null]);
        GenerateReport::dispatch($export->fresh());

        return redirect()->route('reports.index')->with('success', 'Report requeued.');
    }

    public function download(Request $request, ReportExport $export)
    {
        abort_unless(
            (int) $export->user_id === (int) $request->user()->id || $request->user()->hasPermission('reports.export'),
            403,
        );
        abort_unless($export->status === 'DONE' && $export->filename && Storage::disk('local')->exists($export->filename), 404);

        // Downloads are GET, so the HTTP-write audit middleware never sees
        // them — record the access here instead (Plan_revision §Phase 2.5).
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'download report',
            'auditable_type' => ReportExport::class,
            'auditable_id' => $export->id,
            'old_values' => null,
            'new_values' => ['filename' => $export->download_name ?? $export->filename],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Storage::disk('local')->download($export->filename, $export->download_name ?? 'report.pdf');
    }
}
