<?php

namespace App\Jobs;

use App\Models\FreewifiImportBatch;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, Queueable;

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
}
