<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteStatusEventsTest extends TestCase
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
            'location_name' => 'Event Site',
            'ap_site_code' => 'AP-EV-1',
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
        ]);
    }

    public function test_down_status_opens_event_and_recovery_closes_it(): void
    {
        $site = $this->site();

        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->subDays(2)->toDateString(), 'status' => 'UP']);
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->subDay()->toDateString(), 'status' => 'DOWN']);

        $event = SiteStatusEvent::where('site_id', $site->id)->whereNull('resolved_at')->sole();
        $this->assertSame('UP', $event->from_status);
        $this->assertSame('DOWN', $event->to_status);

        // Recovery closes the episode.
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'UP']);
        $this->assertNotNull($event->fresh()->resolved_at);
        $this->assertSame(1, SiteStatusEvent::where('site_id', $site->id)->whereNotNull('resolved_at')->count());
        $this->assertSame(0, SiteStatusEvent::where('site_id', $site->id)->whereNull('resolved_at')->count());
    }

    public function test_consecutive_down_statuses_keep_a_single_open_event(): void
    {
        $site = $this->site();

        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->subDay()->toDateString(), 'status' => 'DOWN']);
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'DOWN_SERVER']);

        $this->assertSame(1, SiteStatusEvent::count());
        $this->assertSame('DOWN_SERVER', SiteStatusEvent::sole()->to_status);
    }

    public function test_heartbeat_resolves_open_heartbeat_lost_events(): void
    {
        $site = $this->site();
        $user = User::factory()->create();
        $token = $user->createToken('probe')->plainTextToken;

        SiteStatusEvent::create([
            'site_id' => $site->id,
            'to_status' => 'DOWN',
            'started_at' => now()->subHour(),
            'cause' => 'heartbeat_lost',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', ['site_code' => 'AP-EV-1', 'status' => 'UP'])
            ->assertOk();

        $this->assertSame(0, SiteStatusEvent::where('site_id', $site->id)->whereNull('resolved_at')->count());
    }

    public function test_heartbeat_upsert_closes_manual_down_episode_on_recovery(): void
    {
        $site = $this->site();
        $user = User::factory()->create();
        $token = $user->createToken('probe')->plainTextToken;

        // Encoder marked the site DOWN — episode open.
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'DOWN']);
        $this->assertSame(1, SiteStatusEvent::whereNull('resolved_at')->count());

        // A probe reporting UP flips the daily row; the episode closes.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', ['site_code' => 'AP-EV-1', 'status' => 'UP'])
            ->assertOk();

        $this->assertSame(0, SiteStatusEvent::whereNull('resolved_at')->count());
    }
}
