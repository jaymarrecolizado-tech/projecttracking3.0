<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStatusEvent extends Model
{
    protected $fillable = ['site_id', 'from_status', 'to_status', 'started_at', 'resolved_at', 'cause'];

    protected $casts = ['started_at' => 'datetime', 'resolved_at' => 'datetime'];

    /** Statuses that mean "site is effectively down". */
    public const DOWN_STATUSES = ['DOWN', 'DOWN_SERVER'];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isDown(): bool
    {
        return in_array($this->to_status, self::DOWN_STATUSES, true);
    }
}
