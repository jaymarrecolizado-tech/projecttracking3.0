<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateCombinedReportRequest;
use App\Http\Requests\GenerateProvinceReportRequest;
use App\Http\Requests\GenerateScopedReportRequest;
use App\Jobs\GenerateReport;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ReportExport;
use App\Services\GeoFilterOptions;
use App\Services\ReportAnalytics;
use App\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Build a safe download filename. Province/scope values are user-supplied,
     * so every part is slugified before it reaches a Content-Disposition header.
     *
     * @param  list<mixed>  $parts
     */
    private function downloadName(array $parts): string
    {
        $slug = collect($parts)
            ->map(fn ($part) => Str::slug((string) $part))
            ->filter()
            ->implode('-');

        return ($slug === '' ? 'report' : $slug).'.pdf';
    }

    public function index(Request $request): Response
    {
        $projects = Project::where('is_active', true)->get(['id', 'code', 'name', 'marker_color']);
        $exports = ReportExport::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $projectNames = Project::whereIn('id', $exports->getCollection()
            ->map(fn ($export) => $export->params['project_id'] ?? $export->params['filters']['project_id'] ?? null)
            ->filter()->unique()->all())->pluck('name', 'id');
        $exports->getCollection()->each(
            fn ($export) => $export->setAttribute('scope_line', $this->exportScope($export->params ?? [], $projectNames))
        );

        $geoOptions = app(GeoFilterOptions::class);

        return Inertia::render('Reports/Index', [
            'projects' => $projects,
            'exports' => $exports,
            'queueNotice' => ReportExport::staleQueueMessage(),
            'siteTypes' => $geoOptions->siteTypes(),
            'initialOptions' => $geoOptions->for(),
        ]);
    }

    /**
     * One-line scope for an export row, whatever param shape its type uses.
     *
     * @param  array<string, mixed>  $params
     * @param  Collection<int, string>  $projectNames
     */
    private function exportScope(array $params, Collection $projectNames): string
    {
        $flat = $params + ($params['filters'] ?? []);
        unset($flat['filters'], $flat['sections']);
        if (! empty($flat['project_id']) && empty($flat['project'])) {
            $flat['project'] = $projectNames->get($flat['project_id'], '#'.$flat['project_id']);
        }
        $line = app(ReportAnalytics::class)->describeScope($flat);
        if (! empty($params['sections'])) {
            $line .= ' · Sections: '.implode(', ', $params['sections']);
        }

        return $line;
    }

    public function projectPdf(GenerateScopedReportRequest $request, Project $project): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'project',
            'params' => ['project_id' => $project->id] + $request->scope(),
            'download_name' => $this->downloadName(['project', $project->code, 'summary']),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function provincePdf(GenerateProvinceReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'province',
            'params' => [
                'province' => $validated['province'],
                'project_id' => $validated['project_id'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'download_name' => $this->downloadName(['province', $validated['province'], 'summary']),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function siteTypePdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $filters = $request->scope();
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

    public function barangayCoveragePdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $filters = $request->scope();
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

    public function opsPeriodPdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'ops_period',
            'params' => ['filters' => $request->scope()],
            'download_name' => $this->downloadName(['ops-period', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function fleetPdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'fleet',
            'params' => ['filters' => $request->scope()],
            'download_name' => $this->downloadName(['fleet-inventory', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function incidentsPdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'incidents',
            'params' => ['filters' => $request->scope()],
            'download_name' => $this->downloadName(['incidents', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function progressPdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'progress',
            'params' => ['filters' => $request->scope()],
            'download_name' => $this->downloadName(['progress', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    public function satisfactionPdf(GenerateScopedReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'satisfaction',
            'params' => ['filters' => $request->scope()],
            'download_name' => $this->downloadName(['user-satisfaction', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    /** Report-builder pack: one PDF with the selected analytic sections. */
    public function combinedPdf(GenerateCombinedReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $filters = $request->scope();
        unset($filters['sections']);
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'type' => 'combined',
            'params' => ['filters' => $filters, 'sections' => $validated['sections']],
            'download_name' => $this->downloadName(['ops-pack', now()->format('Y-m-d')]),
        ]);
        GenerateReport::dispatch($export);

        return redirect()->route('reports.index')->with('success', 'Report generation started — the download link will appear below.');
    }

    /** Re-queue a failed export with its original filter set (§Phase 5.5). */
    public function retry(Request $request, ReportExport $export): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);
        abort_unless($export->status === 'FAILED', 422, 'Only failed exports can be retried.');

        $export->update(['status' => 'PENDING', 'error' => null, 'completed_at' => null]);
        GenerateReport::dispatch($export->fresh());

        return redirect()->route('reports.index')->with('success', 'Report requeued.');
    }

    /** CSV companion for an export's annex table — regenerated, never stored. */
    public function downloadCsv(Request $request, ReportExport $export): StreamedResponse
    {
        abort_unless(
            (int) $export->user_id === (int) $request->user()->id || $request->user()->hasPermission('reports.export'),
            403,
        );
        abort_unless($export->status === 'DONE', 404);
        abort_unless($export->type !== 'combined', 422, 'Combined packs have no single table — use a single-pack CSV.');

        $csv = app(ReportingService::class)->exportCsv($export);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'download csv',
            'auditable_type' => ReportExport::class,
            'auditable_id' => $export->id,
            'old_values' => null,
            'new_values' => ['filename' => $csv['name']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->streamDownload(function () use ($csv) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $csv['headings']);
            foreach ($csv['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $csv['name'], ['Content-Type' => 'text/csv']);
    }

    public function download(Request $request, ReportExport $export): StreamedResponse
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
