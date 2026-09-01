<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ReportExport;
use App\Services\ReportingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Generates PDFs off the request cycle — DomPDF on large provinces can
 * easily outlive a web request. Status lands on the ReportExport row,
 * which the Reports page polls until DONE.
 */
class GenerateReport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public ReportExport $export) {}

    public function handle(ReportingService $reportingService): void
    {
        $this->export->update(['status' => 'PROCESSING']);

        try {
            $pdf = match ($this->export->type) {
                'project' => $reportingService->generateProjectSummaryPdf(
                    Project::findOrFail($this->export->params['project_id']),
                ),
                'province' => $reportingService->generateProvinceReport(
                    $this->export->params['province'],
                    $this->export->params['project_id'] ?? null,
                ),
                'site_type' => $reportingService->generateSiteTypeCoverageReport(
                    $this->export->params['filters'] ?? [],
                ),
                default => throw new InvalidArgumentException("Unknown report type '{$this->export->type}'."),
            };

            $filename = 'reports/'.uniqid('report-').'.pdf';
            Storage::disk('local')->put($filename, $pdf->output());

            $this->export->update([
                'status' => 'DONE',
                'filename' => $filename,
                'error' => null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Recorded on the row so the UI can surface it; not rethrown on purpose.
            $this->export->update([
                'status' => 'FAILED',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
        }
    }
}
