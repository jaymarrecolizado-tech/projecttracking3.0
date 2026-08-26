<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupReportExports extends Command
{
    protected $signature = 'reports:cleanup {--days=7 : Age threshold for finished exports}';

    protected $description = 'Delete finished PDF report exports (file + row) older than N days';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $stale = ReportExport::whereIn('status', ['DONE', 'FAILED'])
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($stale as $export) {
            if ($export->filename && Storage::disk('local')->exists($export->filename)) {
                Storage::disk('local')->delete($export->filename);
            }
            $export->delete();
        }

        $this->info("Cleaned up {$stale->count()} report export(s) older than {$this->option('days')} day(s).");

        return self::SUCCESS;
    }
}
