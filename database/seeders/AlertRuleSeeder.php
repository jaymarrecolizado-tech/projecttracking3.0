<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use Illuminate\Database\Seeder;

/**
 * Docs §4.3 default rules. notify_roles names permissions so project-scoped
 * approvers resolve the same way as alerts:down recipients.
 */
class AlertRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Site offline (no heartbeat)',
                'metric' => 'offline_minutes',
                'operator' => '>',
                'threshold' => 10,
                'duration_minutes' => 0,
                'severity' => 'critical',
                'notify_roles' => ['daily.approve'],
            ],
            [
                'name' => 'WAN latency high',
                'metric' => 'latency_ms',
                'operator' => '>',
                'threshold' => 150,
                'duration_minutes' => 30,
                'severity' => 'warning',
                'notify_roles' => ['daily.approve'],
            ],
            [
                'name' => 'Battery critically low',
                'metric' => 'battery_v',
                'operator' => '<',
                'threshold' => 11.8,
                'duration_minutes' => 0,
                'severity' => 'critical',
                'notify_roles' => ['daily.approve'],
            ],
            [
                'name' => 'Firmware outdated',
                'metric' => 'firmware_outdated',
                'operator' => '>=',
                'threshold' => 1,
                'duration_minutes' => 0,
                'severity' => 'info',
                'notify_roles' => ['users.manage'],
            ],
            [
                'name' => 'Bandwidth congestion',
                'metric' => 'bandwidth_pct',
                'operator' => '>',
                'threshold' => 85,
                'duration_minutes' => 0,
                'severity' => 'warning',
                'notify_roles' => ['daily.approve'],
            ],
        ];

        foreach ($rules as $rule) {
            AlertRule::updateOrCreate(['name' => $rule['name']], $rule);
        }
    }
}
