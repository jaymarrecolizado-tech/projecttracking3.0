<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        // Region II provinces only — REGIONS_BY_PROVINCE on Site cannot
        // attribute anything else, and tests assert on region.
        $province = fake()->randomElement(['Batanes', 'Cagayan', 'Isabela', 'Nueva Vizcaya', 'Quirino']);

        return [
            'project_id' => Project::factory(),
            'ap_site_code' => 'F-'.strtoupper(fake()->unique()->lexify('????????')),
            'location_name' => fake()->company().' Site',
            'province' => $province,
            'municipality' => fake()->city(),
            'barangay' => 'Barangay '.fake()->streetName(),
            'latitude' => fake()->latitude(16.0, 19.0),
            'longitude' => fake()->longitude(120.0, 123.0),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
