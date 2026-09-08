<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDailyStatus>
 */
class SiteDailyStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'date' => today()->toDateString(),
            'status' => fake()->randomElement(config('daily_status.observed')),
            'entry_status' => 'DRAFT',
            'created_by' => User::factory(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['entry_status' => 'LOCKED']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['entry_status' => 'APPROVED']);
    }
}
