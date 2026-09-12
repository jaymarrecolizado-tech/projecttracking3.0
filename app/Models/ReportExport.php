<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'params',
        'status',
        'filename',
        'download_name',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Warn when queued work is going nowhere: a PENDING export older than 5
     * minutes means no worker is draining the queue (dev: start
     * `php artisan queue:work`; prod: check supervisor). Displayed on the
     * Reports page so a dead worker can never look like a slow one.
     */
    public static function staleQueueMessage(): ?string
    {
        $oldest = static::where('status', 'PENDING')->min('created_at');
        if ($oldest === null) {
            return null;
        }
        // Carbon 3 diffs are signed by default — absolute elapsed minutes.
        $minutes = (int) now()->diffInMinutes(Carbon::parse($oldest), true);
        if ($minutes < 5) {
            return null;
        }

        return "A report has been waiting {$minutes} minutes — the queue worker looks down, so nothing will generate until it runs.";
    }
}
