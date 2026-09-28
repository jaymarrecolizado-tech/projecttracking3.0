<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Makes `/up` mean "the app works", not just "PHP is alive".
 *
 * Laravel's health endpoint dispatches `DiagnosingHealth` and returns 200
 * unless a listener throws — so throwing here is how a check reports failure.
 * Without it, the external monitor this app still needs (Plan.md → F3) would be
 * pinging a URL that answers 200 while every page in the app 500s: a dead
 * database, or a `storage/` that lost its permissions after an upload, both
 * look identical to a monitor from the outside.
 *
 * Two checks, not more. A third (cache) would not fail in any way the other two
 * do not already catch: the cache driver here is file or database, so it shares
 * the disk and the connection being tested above.
 *
 * Deliberately unauthenticated and cheap: no schema introspection, no queries
 * beyond `select 1`, and no writes.
 */
class CheckSystemHealth
{
    public function __invoke(DiagnosingHealth $event): void
    {
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            throw new RuntimeException('Database unreachable: '.$e->getMessage(), previous: $e);
        }

        if (! File::isWritable(storage_path())) {
            throw new RuntimeException('storage/ is not writable — sessions, logs and report writes will fail.');
        }
    }
}
