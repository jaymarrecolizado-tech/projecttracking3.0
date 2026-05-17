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
    public function __construct(FreewifiImportBatch $batch, string $filePath)
    {
        $this->batch = $batch;
        $this->filePath = $filePath;
    }
    public function handle(ImportService $importService): void
    {
        $importService->processImport($this->batch, $this->filePath);
    }
}
