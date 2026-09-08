<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\FreewifiImportBatch;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Excel ingest off the request cycle. Imports are big but deterministic:
 * retry a bounded number of times, then mark the batch failed so the UI and
 * CleanupStaleImports never have to guess what happened to it.
 */
class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public FreewifiImportBatch $batch;

    public string $filePath;

    public string $type;

    public ?int $actorId;

    /** $actorId preserves who triggered the import — auth() is unavailable on workers. */
    public function __construct(FreewifiImportBatch $batch, string $filePath, string $type = 'sites', ?int $actorId = null)
    {
        $this->batch = $batch;
        $this->filePath = $filePath;
        $this->type = $type;
        $this->actorId = $actorId;
    }

    public function handle(ImportService $importService): void
    {
        try {
            match ($this->type) {
                'devices' => $importService->processDeviceImport($this->batch, $this->filePath, $this->actorId),
                'region_workbook' => $importService->processRegionWorkbook($this->batch, $this->filePath, $this->actorId),
                default => $importService->processImport($this->batch, $this->filePath),
            };
        } finally {
            // The spreadsheet is only read once; drop it so uploads don't accumulate.
            if (is_file($this->filePath)) {
                @unlink($this->filePath);
            }
        }
    }

    /** Last resort after all retries — the batch row must not stay "running". */
    public function failed(?\Throwable $exception = null): void
    {
        $this->batch->forceFill([
            'job_status' => 'FAILED',
            'completed_at' => now(),
            'error_log' => array_merge($this->batch->error_log ?? [], [[
                'message' => 'Import failed after '.$this->tries.' attempts: '.($exception?->getMessage() ?? 'unknown error'),
            ]]),
        ])->save();

        Log::error('Excel import failed permanently.', [
            'batch' => $this->batch->id,
            'type' => $this->type,
            'error' => $exception?->getMessage(),
        ]);

        // Queue-side actions are audited like their HTTP counterparts
        // (Plan_revision §Phase 2.5).
        AuditLog::create([
            'user_id' => $this->actorId,
            'action' => "import failed ({$this->type})",
            'auditable_type' => FreewifiImportBatch::class,
            'auditable_id' => $this->batch->id,
            'old_values' => null,
            'new_values' => ['error' => $exception?->getMessage() ?? 'unknown error'],
            'ip_address' => null,
            'user_agent' => 'queue',
        ]);
    }
}
