<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Retention for the log files Laravel's daily channel writes (Plan.md F3).
 *
 * Deliberately a command and not a host logrotate rule: the daily channel
 * already rotates by *name* (laravel-2026-09-26.log, one file per day), so
 * there is nothing for logrotate to rotate — it would only ever rename each
 * file once and then never expire it. What is actually missing is expiry, and
 * a date-named file set is what `find -mtime` is for. Running it from the
 * scheduler also guarantees it executes as the site user, with the right
 * ownership, instead of depending on a hand-written root crontab.
 *
 * ponytail: a plain `find -delete` in cron would do the same job. This exists
 * so the window is versioned, testable and scheduled with everything else —
 * drop it and put the find in root's crontab if the server is managed that way.
 *
 * --path exists so the test can prune a temp directory instead of the
 * developer's real storage/logs.
 */
class PruneLogs extends Command
{
    protected $signature = 'logs:prune
        {--days=30 : Keep this many days of log files}
        {--path= : Directory to prune (defaults to storage/logs)}';

    protected $description = 'Delete rotated Laravel log files older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $directory = (string) ($this->option('path') ?: storage_path('logs'));

        if (! File::isDirectory($directory)) {
            $this->warn("No log directory at {$directory} — nothing to prune.");

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->getTimestamp();

        // Only the dated daily-channel files. laravel.log is the file currently
        // being written, and stderr/stdout belong to supervisor.
        $deleted = 0;
        foreach (File::glob($directory.'/laravel-*.log') as $path) {
            if (File::lastModified($path) < $cutoff) {
                File::delete($path);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} log file(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
