<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceModel extends Model
{
    protected $fillable = ['manufacturer', 'model_name', 'model_number', 'type', 'wifi_standard',
        'specs', 'datasheet_url', 'photo_path', 'is_active'];

    protected $casts = [
        'specs' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
