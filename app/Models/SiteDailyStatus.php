<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDailyStatus extends Model
{
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

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeUp($query)
    {
        return $query->where('status', 'UP');
    }
}
