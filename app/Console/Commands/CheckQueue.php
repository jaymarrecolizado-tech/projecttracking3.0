<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;

class CheckQueue extends Command
{
    protected $signature = 'queue:check {--minutes=5 : PENDING age in minutes that counts as stuck}';

    protected $description = 'Fail when queued work is stuck (no worker draining the queue)';

    public function handle(): int
    {
        $message = ReportExport::staleQueueMessage((int) $this->option('minutes'));

        if ($message === null) {
            $this->info('Queue draining — no stuck PENDING work.');

            return self::SUCCESS;
        }

        $this->error($message);

        return self::FAILURE;
    }
}
