<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only demo content so the map/dashboard have something to plot.
 * Run manually: php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $projectId = DB::table('projects')->where('code', 'FREEWIFI')->value('id');
        if (! $projectId) {
            $this->command->error('FREEWIFI project not found — run ProjectSeeder first.');

            return;
        }

        $sites = [
            // [location, barangay, municipality, province, region, island, lat, lng, status, activated]
            ['Rizal Park Free WiFi', 'Ermita', 'Manila', 'Metro Manila', 'NCR', 'Luzon', 14.5826, 120.9772, 'active', '2024-03-12'],
            ['Quezon City Hall Plaza', 'Diliman', 'Quezon City', 'Metro Manila', 'NCR', 'Luzon', 14.6512, 121.0499, 'active', '2024-01-25'],
            ['Burnham Park Pavilion', 'Baguio Proper', 'Baguio City', 'Benguet', 'CAR', 'Luzon', 16.4086, 120.5960, 'active', '2023-11-08'],
            ['Calle Crisologo Corner', 'Vigan Centro', 'Vigan City', 'Ilocos Sur', 'Region I', 'Luzon', 17.5747, 120.3893, 'maintenance', '2024-02-14'],
            ['Tuguegarao Public Market', 'Centro 2', 'Tuguegarao City', 'Cagayan', 'Region II', 'Luzon', 17.6132, 121.7269, 'active', '2023-09-30'],
            ['Legazpi Boulevard Deck', 'Puro', 'Legazpi City', 'Albay', 'Region V', 'Luzon', 13.1391, 123.7438, 'active', '2024-04-02'],
            ['Cebu IT Park Square', 'Apas', 'Cebu City', 'Cebu', 'Region VII', 'Visayas', 10.3213, 123.9024, 'active', '2023-12-19'],
            ['Iloilo Esplanade Stage', 'San Rafael', 'Iloilo City', 'Iloilo', 'Region VI', 'Visayas', 10.6969, 122.5630, 'active', '2024-01-08'],
            ['Capitol Lagoon Park', 'Capitalville', 'Bacolod City', 'Negros Occidental', 'Region VI', 'Visayas', 10.6668, 122.9511, 'inactive', '2023-10-21'],
            ['Balyuan Amphitheater', 'Barangay 3', 'Tacloban City', 'Leyte', 'Region VIII', 'Visayas', 11.2442, 125.0030, 'active', '2024-03-01'],
            ["People's Park Gazebo", 'Central', 'Davao City', 'Davao del Sur', 'Region XI', 'Mindanao', 7.0687, 125.6090, 'active', '2023-11-27'],
            ['Divisoria Kiosk Row', 'Poblacion', 'Cagayan de Oro City', 'Misamis Oriental', 'Region X', 'Mindanao', 8.4772, 124.6452, 'active', '2024-02-05'],
            ['Paseo del Mar Fountain', 'Zone IV', 'Zamboanga City', 'Zamboanga del Sur', 'Region IX', 'Mindanao', 6.9055, 122.0749, 'planned', null],
            ['Provincial Capitol Grounds', 'Rosary Heights', 'Cotabato City', 'Maguindanao', 'BARMM', 'Mindanao', 7.2047, 124.2310, 'active', '2024-05-16'],
            ['Surigao Boardwalk', 'Taft', 'Surigao City', 'Surigao del Norte', 'Region XIII', 'Mindanao', 9.7869, 125.4943, 'active', '2024-04-22'],
        ];

        $created = [];
        foreach ($sites as $i => [$name, $brgy, $mun, $prov, $region, $island, $lat, $lng, $status, $activated]) {
            $created[$name] = Site::updateOrCreate(
                ['project_id' => $projectId, 'ap_site_code' => sprintf('FW-%s-%03d', substr($island, 0, 2), $i + 1)],
                [
                    'location_name' => $name,
                    'site_type' => str_contains($name, 'Park') || str_contains($name, 'Plaza') ? 'public_plaza' : 'community_center',
                    'barangay' => $brgy,
                    'municipality' => $mun,
                    'province' => $prov,
                    'region' => $region,
                    'island_group' => $island,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'status' => $status,
                    'date_of_activation' => $activated,
                    'isp_provider' => ['PLDT', 'Converge', 'Globe', 'Sky Fiber'][$i % 4],
                    'last_mile_tech' => ['Fiber Optic', 'Radio Link', 'VSAT'][$i % 3],
                    'bw_download_cir' => [50, 100, 200][$i % 3],
                ]
            );
        }

        // Last 7 days of telemetry — UP-heavy with a couple of incidents
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        foreach ($created as $name => $site) {
            if ($site->status !== 'active') {
                continue;
            }
            $downDay = in_array($name, ['Calle Crisologo Corner']) ? 2 : (in_array($name, ['Balyuan Amphitheater']) ? 5 : null);
            for ($d = 7; $d >= 1; $d--) {
                $date = Carbon::today()->subDays($d);
                $isDown = $downDay === $d || ($name === "People's Park Gazebo" && $d === 3);
                SiteDailyStatus::updateOrCreate(
                    ['site_id' => $site->id, 'date' => $date->toDateString()],
                    [
                        'status' => $isDown ? 'DOWN' : 'UP',
                        'bandwidth_utilization_mbps' => $isDown ? 0 : round($site->bw_download_cir * (0.35 + (($site->id * $d) % 40) / 100), 1),
                        'total_unique_users' => $isDown ? 0 : 80 + (($site->id * 37 + $d * 53) % 420),
                        'uptime_percent' => $isDown ? 62.5 : 99.2,
                        'created_by' => $admin?->id,
                        'submitted_at' => now(),
                        'entry_status' => 'APPROVED',
                        'approved_by' => $admin?->id,
                        'approved_at' => now(),
                    ]
                );
            }
        }

        // Deploy the demo device so Equipment tabs / assignments render
        $device = Device::where('asset_tag', 'FW-0001')->first();
        if ($device && $target = $created['Tuguegarao Public Market'] ?? null) {
            DeviceDeployment::firstOrCreate(
                ['device_id' => $device->id, 'site_id' => $target->id],
                ['role_at_site' => 'primary_ap', 'installed_at' => now()->subMonths(2), 'installed_by' => $admin?->id]
            );
            $device->update(['status' => 'deployed']);
        }

        $this->command->info(count($created).' demo sites seeded with 7-day telemetry.');
    }
}
