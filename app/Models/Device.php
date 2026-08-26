<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = ['device_model_id', 'asset_tag', 'serial_number', 'mac_address',
        'firmware_version', 'status', 'condition', 'purchase_order_no',
        'supplier', 'unit_cost', 'purchased_at', 'warranty_until', 'notes'];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'purchased_at' => 'date',
        'warranty_until' => 'date',
    ];

    /** @return BelongsTo<DeviceModel, $this> */
    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    /** @return HasMany<DeviceDeployment, $this> */
    public function deployments(): HasMany
    {
        return $this->hasMany(DeviceDeployment::class);
    }

    /** @return HasOne<DeviceDeployment, $this> */
    public function currentDeployment(): HasOne
    {
        return $this->hasOne(DeviceDeployment::class)->whereNull('removed_at')->latestOfMany('installed_at');
    }

    /** @return HasMany<MaintenanceLog, $this> */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('status', 'in_stock');
    }

    public function scopeDeployed(Builder $q): Builder
    {
        return $q->where('status', 'deployed');
    }

    public function isActive(): bool
    {
        return $this->status === 'deployed' && $this->currentDeployment !== null;
    }
}
