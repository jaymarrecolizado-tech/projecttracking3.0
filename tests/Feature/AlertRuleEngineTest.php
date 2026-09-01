<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\DeviceMetric;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private function site(): Site
    {
        $this->seed(RolePermissionSeeder::class);
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
        ]);

        return Site::create([
            'project_id' => $project->id,
            'location_name' => 'Rule Site',
            'ap_site_code' => 'AP-RU-1',
            'municipality' => 'Aparri',
            'province' => 'Cagayan',
            'bw_download_cir' => 20,
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
        ]);
    }

    private function metric(Site $site, array $attributes): void
    {
        DeviceMetric::create(array_merge([
            'site_id' => $site->id,
            'ts' => now(),
        ], $attributes));
    }

    public function test_latency_rule_fires_after_duration_and_notifies(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config()->set('monitoring.telegram.bot_token', 't');
        config()->set('monitoring.telegram.chat_id', '1');

        $site = $this->site();
        $approver = User::factory()->create();
        $approver->roles()->attach(1);

        AlertRule::create([
            'name' => 'WAN latency high', 'metric' => 'latency_ms', 'operator' => '>',
            'threshold' => 150, 'duration_minutes' => 30, 'severity' => 'warning',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ]);

        // Fresh violation only — window not held long enough yet.
        $this->metric($site, ['latency_ms' => 300, 'ts' => now()->subMinutes(5)]);
        $this->artisan('alerts:evaluate')->expectsOutputToContain('0 fired');
        $this->assertSame(0, Alert::count());

        // A sample older than the duration proves the condition has held.
        $this->metric($site, ['latency_ms' => 280, 'ts' => now()->subMinutes(45)]);

        $this->artisan('alerts:evaluate')->expectsOutputToContain('1 fired');
        $alert = Alert::sole();
        // The alert reports the LATEST violating reading.
        $this->assertEqualsWithDelta(300.0, (float) $alert->context['observed'], 0.01);
        $this->assertNull($alert->resolved_at);
        Http::assertSentCount(1);
    }

    public function test_recovery_auto_resolves_open_alert(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $site = $this->site();
        $approver = User::factory()->create();
        $approver->roles()->attach(1);
        AlertRule::create([
            'name' => 'Battery critically low', 'metric' => 'battery_v', 'operator' => '<',
            'threshold' => 11.8, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ]);

        $this->metric($site, ['battery_v' => 11.2, 'ts' => now()->subMinutes(3)]);
        $this->artisan('alerts:evaluate');
        $this->assertSame(1, Alert::whereNull('resolved_at')->count());

        // Fresh healthy reading resolves the alert without re-notifying.
        Mail::shouldReceive('raw')->never();
        $this->metric($site, ['battery_v' => 12.9, 'ts' => now()]);
        $this->artisan('alerts:evaluate');
        $this->assertSame(1, Alert::whereNotNull('resolved_at')->count());
    }

    public function test_offline_rule_fires_when_last_beat_is_stale(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $site = $this->site();
        $approver = User::factory()->create();
        $approver->roles()->attach(1);
        AlertRule::create([
            'name' => 'Site offline (no heartbeat)', 'metric' => 'offline_minutes', 'operator' => '>',
            'threshold' => 10, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ]);

        $this->metric($site, ['latency_ms' => 20, 'ts' => now()->subMinutes(45)]);

        $this->artisan('alerts:evaluate')->expectsOutputToContain('1 fired');
        $alert = Alert::sole();
        $this->assertGreaterThan(10, $alert->context['observed']);
    }

    public function test_bandwidth_rule_uses_cir_percentage(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $site = $this->site(); // CIR 20 Mbps
        $approver = User::factory()->create();
        $approver->roles()->attach(1);
        AlertRule::create([
            'name' => 'Bandwidth congestion', 'metric' => 'bandwidth_pct', 'operator' => '>',
            'threshold' => 85, 'duration_minutes' => 0, 'severity' => 'warning',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ]);

        // 18/20 = 90% > 85% via today's daily status.
        SiteDailyStatus::create([
            'site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'UP',
            'bandwidth_utilization_mbps' => 18,
        ]);

        $this->artisan('alerts:evaluate')->expectsOutputToContain('1 fired');
        $this->assertSame(1, Alert::count());
    }
}
