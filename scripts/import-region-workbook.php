<?php

/** One-off: import the real REGION II workbook into the dev database. */

use App\Models\Device;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Services\ImportService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(ImportService::class);
$batch = $service->beginImport('REGION II SITE STATUS 2026.xlsx', 1, 'region_workbook');

$start = microtime(true);
$service->processRegionWorkbook($batch, __DIR__.'/../Reference/REGION II SITE STATUS 2026.xlsx', 1);
$batch->refresh();

echo 'status: '.$batch->job_status.PHP_EOL;
echo 'took: '.round(microtime(true) - $start, 1).'s'.PHP_EOL;
echo 'rows_success: '.$batch->rows_success.' | failed: '.$batch->rows_failed.PHP_EOL;
echo 'sites: '.Site::count().' | devices: '.Device::count().' | daily statuses: '.SiteDailyStatus::count().' | projects: '.Project::count().PHP_EOL;
echo 'day-status mix: '.SiteDailyStatus::groupBy('status')->selectRaw('status, COUNT(*) n')->pluck('n', 'status')->toJson().PHP_EOL;
echo 'lifecycle mix: '.Site::groupBy('lifecycle_status')->selectRaw('lifecycle_status, COUNT(*) n')->pluck('n', 'lifecycle_status')->toJson().PHP_EOL;
echo 'projects: '.Project::pluck('name')->implode(' | ').PHP_EOL;

if ($batch->rows_failed > 0) {
    echo 'errors: '.json_encode(array_slice($batch->error_log ?? [], 0, 5), JSON_PRETTY_PRINT).PHP_EOL;
}
