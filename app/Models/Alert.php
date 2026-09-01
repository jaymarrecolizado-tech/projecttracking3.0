<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = ['rule_id', 'site_id', 'device_id', 'triggered_at', 'acknowledged_at',
        'acknowledged_by', 'resolved_at', 'escalation_level', 'context'];

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'context' => 'array',
    ];

    /** @return BelongsTo<AlertRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}
