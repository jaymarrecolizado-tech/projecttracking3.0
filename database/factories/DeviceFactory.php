<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_model_id' => DeviceModel::factory(),
            'asset_tag' => 'DICT-'.strtoupper(fake()->unique()->lexify('????????')),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN-#####???')),
            'mac_address' => fake()->macAddress(),
            'status' => 'in_stock',
        ];
    }

    public function deployed(): static
    {
        return $this->state(fn () => ['status' => 'deployed']);
    }
}
