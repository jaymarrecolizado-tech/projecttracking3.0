<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $fillable = ['name', 'metric', 'operator', 'threshold', 'duration_minutes',
        'severity', 'notify_roles', 'is_active'];

    protected $casts = [
        'threshold' => 'decimal:2',
        'notify_roles' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
