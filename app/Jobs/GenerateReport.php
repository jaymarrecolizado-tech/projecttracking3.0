<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ReportExport;
use App\Services\ReportingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

    /** DomPDF on a province-sized export is slow but deterministic — retry briefly. */
    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

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
                'barangay_coverage' => $reportingService->generateBarangayCoverageReport(
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
            // Recorded on the row so the UI can surface it…
            $this->export->update([
                'status' => 'FAILED',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            // …but permanent failures must stop here: an unknown report type or a
            // deleted project will fail identically on every retry. Everything
            // else is rethrown so it lands in failed_jobs and can be alerted on
            // instead of silently disappearing.
            $permanent = $e instanceof InvalidArgumentException
                || $e instanceof ModelNotFoundException;

            if (! $permanent) {
                throw $e;
            }
        }
    }

    /** Last resort after all retries — keep the row honest about why it died. */
    public function failed(?\Throwable $exception = null): void
    {
        $this->export->update([
            'status' => 'FAILED',
            'error' => mb_substr(
                $exception !== null
                    ? 'Giving up after '.$this->tries.' attempts: '.$exception->getMessage()
                    : 'Giving up after '.$this->tries.' attempts.',
                0,
                2000,
            ),
            'completed_at' => now(),
        ]);

        Log::error('Report generation failed permanently.', [
            'export_id' => $this->export->id,
            'type' => $this->export->type,
            'exception' => $exception?->getMessage(),
        ]);

        // Queue-side failures are audited like their HTTP counterparts
        // (Plan_revision §Phase 2.5).
        AuditLog::create([
            'user_id' => $this->export->user_id,
            'action' => 'report generation failed',
            'auditable_type' => ReportExport::class,
            'auditable_id' => $this->export->id,
            'old_values' => null,
            'new_values' => ['error' => $exception?->getMessage() ?? 'unknown error'],
            'ip_address' => null,
            'user_agent' => 'queue',
        ]);
    }
}
