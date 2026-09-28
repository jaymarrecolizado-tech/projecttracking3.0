<?php

namespace App\Models;

use Database\Factories\SiteDailyStatusFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDailyStatus extends Model
{
    /** @use HasFactory<SiteDailyStatusFactory> */
    use HasFactory;

    protected $fillable = ['site_id', 'date', 'status', 'total_unique_users',
        'bandwidth_utilization_mbps', 'uptime_percent',
        'notes', 'entry_status', 'submitted_at', 'approved_by', 'approved_at', 'created_by'];

    protected $casts = ['date' => 'date'];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @param  Builder<SiteDailyStatus>  $query */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('date', $date);
    }

    /** @param  Builder<SiteDailyStatus>  $query */
    public function scopeUp(Builder $query): void
    {
        $query->where('status', 'UP');
    }
}
