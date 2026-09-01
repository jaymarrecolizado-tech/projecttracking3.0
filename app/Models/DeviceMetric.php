<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['device_id', 'site_id', 'ts', 'uptime_s', 'cpu_pct', 'mem_pct',
        'latency_ms', 'clients', 'rx_mbps', 'tx_mbps', 'battery_v', 'solar_w',
        'power_source', 'firmware', 'raw'];

    protected $casts = [
        'ts' => 'datetime',
        'raw' => 'array',
    ];

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
