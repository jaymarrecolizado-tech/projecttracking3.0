<?php

namespace Tests\Feature;

use App\Mail\StatusReminderMail;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DailyOpsTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create([
            'code' => $code, 'name' => "Project {$code}", 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
        ]);
    }

    private function site(Project $project, string $name = 'Ops Site'): Site
    {
        return Site::create([
            'project_id' => $project->id, 'location_name' => $name,
            'latitude' => 17.6, 'longitude' => 121.7, 'status' => 'active',
            'ap_site_code' => 'OPS-'.substr(md5($name.uniqid()), 0, 8),
        ]);
    }

    private function userWithRole(string $role, ?int $projectId = null): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $roleId = Role::where('name', $role)->value('id');
        $user->roles()->attach($roleId, ['project_id' => $projectId]);

        return $user;
    }

    private function payload(Site $site, string $action, string $status = 'UP', ?string $date = null): array
    {
        return [
            'date' => $date ?? today()->toDateString(),
            'action' => $action,
            'entries' => [['site_id' => $site->id, 'status' => $status]],
        ];
    }

    public function test_board_scopes_sites_to_encoders_assigned_project(): void
    {
        $projectA = $this->project('OPS-A');
        $projectB = $this->project('OPS-B');
        $this->site($projectA, 'Alpha');
        $this->site($projectB, 'Bravo');

        $encoder = $this->userWithRole('encoder', $projectA->id);

        $this->actingAs($encoder)->get('/daily-ops')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DailyOps/Index')
                ->has('rows', 1)
                ->where('rows.0.location_name', 'Alpha'));
    }

    public function test_encoder_submit_flow_records_submitted_state(): void
    {
        $project = $this->project('OPS-C');
        $site = $this->site($project);
        $encoder = $this->userWithRole('encoder', $project->id);

        $this->actingAs($encoder)->post('/daily-ops/batch', $this->payload($site, 'submit'))
            ->assertRedirect();

        $record = SiteDailyStatus::where('site_id', $site->id)->first();
        $this->assertSame('SUBMITTED', $record->entry_status);
        $this->assertSame('UP', $record->status);
        $this->assertNotNull($record->submitted_at);
        $this->assertNull($record->approved_at);
    }

    public function test_approve_requires_daily_approve_permission(): void
    {
        $project = $this->project('OPS-D');
        $site = $this->site($project);
        $encoder = $this->userWithRole('encoder', $project->id);

        $this->actingAs($encoder)->post('/daily-ops/batch', $this->payload($site, 'submit'));

        // Encoder attempts to approve — skipped.
        $this->actingAs($encoder)->post('/daily-ops/batch', $this->payload($site, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('SUBMITTED', $site->dailyStatuses()->first()->entry_status);

        // Manager approves — allowed and locks in approval metadata.
        $manager = $this->userWithRole('project_manager', $project->id);
        $this->actingAs($manager)->post('/daily-ops/batch', $this->payload($site, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $approved = $site->dailyStatuses()->first();
        $this->assertSame('APPROVED', $approved->entry_status);
        $this->assertSame($manager->id, $approved->approved_by);
    }

    public function test_locked_rows_reject_manual_edits_and_heartbeats(): void
    {
        $project = $this->project('OPS-E');
        $site = $this->site($project);
        $admin = $this->userWithRole('admin');

        SiteDailyStatus::create([
            'site_id' => $site->id, 'date' => today(), 'status' => 'DOWN',
            'entry_status' => 'LOCKED',
        ]);

        $token = $admin->createToken('probe')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', ['site_code' => $site->ap_site_code, 'status' => 'UP'])
            ->assertStatus(409)
            ->assertJson(['ok' => false, 'error' => 'locked']);

        $this->flushHeaders();
        Auth::guard('sanctum')->forgetUser();

        $this->actingAs($admin)->post('/daily-ops/batch', $this->payload($site, 'save_draft', 'UP'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $record = $site->dailyStatuses()->first();
        $this->assertSame('DOWN', $record->status);
        $this->assertSame('LOCKED', $record->entry_status);
    }

    public function test_snapshot_fills_missing_actives_and_is_idempotent(): void
    {
        $project = $this->project('OPS-F');
        $reported = $this->site($project, 'Reported');
        $silent = $this->site($project, 'Silent');
        $inactive = $this->site($project, 'Inactive');
        $inactive->update(['status' => 'inactive']);

        SiteDailyStatus::create(['site_id' => $reported->id, 'date' => today(), 'status' => 'UP']);

        $this->artisan('statuses:snapshot')->assertSuccessful();
        $this->artisan('statuses:snapshot')->assertSuccessful(); // idempotent

        $this->assertSame(1, SiteDailyStatus::where('site_id', $silent->id)->count());
        $snapshotted = SiteDailyStatus::where('site_id', $silent->id)->first();
        $this->assertSame('NO_DATA', $snapshotted->status);
        $this->assertSame(1, SiteDailyStatus::where('site_id', $reported->id)->count());
        $this->assertSame(0, SiteDailyStatus::where('site_id', $inactive->id)->count());
    }

    public function test_reminder_mails_project_encoders_about_unreported_sites(): void
    {
        Mail::fake();
        $projectA = $this->project('OPS-G');
        $this->site($projectA, 'Unreported One');
        $this->userWithRole('encoder', $projectA->id); // scoped encoder gets mail

        $this->artisan('statuses:remind')->assertSuccessful();

        Mail::assertSent(StatusReminderMail::class, 1);
        Mail::assertSent(StatusReminderMail::class, fn (StatusReminderMail $mail) => $mail->perProject === ['Project OPS-G' => 1]);
    }
}
