<?php

namespace Tests\Feature;

use App\Jobs\ProcessExcelImport;
use App\Models\AuditLog;
use App\Models\FreewifiImportBatch;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Plan_revision §Phase 2 — authorization and audit.
 *
 * - entry_status is server-controlled: no daily.edit user can self-approve or
 *   lock a record through the store endpoint.
 * - Approve/lock are dedicated endpoints gated on daily.approve.
 * - LOCKED rows reject edits (the policy was dead code before the rename —
 *   auto-discovery never bound it).
 * - Read routes without sites.view/daily.view are 403, not open to any
 *   authenticated user.
 * - Heartbeat tokens must carry the heartbeat ability and may not clobber
 *   APPROVED rows.
 * - Report downloads and queue failures leave audit rows.
 */
class DailyStatusWorkflowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'project_id' => $this->project->id,
            'location_name' => 'Workflow Site',
            'province' => 'Cagayan',
            'municipality' => 'Aparri',
            'barangay' => 'Tobias',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role, ?int $projectId = null): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('name', $role)->value('id'),
            ['project_id' => $projectId],
        );

        return $user;
    }

    private function statusRow(string $entryStatus = 'DRAFT'): SiteDailyStatus
    {
        return SiteDailyStatus::create([
            'site_id' => $this->site->id,
            'date' => today()->toDateString(),
            'status' => 'UP',
            'entry_status' => $entryStatus,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_entry_status_is_not_client_writable(): void
    {
        $encoder = $this->userWithRole('encoder');

        $response = $this->actingAs($encoder)->post('/daily-statuses', [
            'site_id' => $this->site->id,
            'date' => today()->toDateString(),
            'status' => 'UP',
            'entry_status' => 'APPROVED', // must be ignored
        ]);

        $response->assertRedirect();
        $this->assertSame('DRAFT', $this->site->dailyStatuses()->first()->entry_status);
    }

    public function test_encoder_cannot_approve_or_lock(): void
    {
        $encoder = $this->userWithRole('encoder');
        $row = $this->statusRow();

        $this->actingAs($encoder)->post("/daily-statuses/{$row->id}/approve")->assertForbidden();
        $this->actingAs($encoder)->post("/daily-statuses/{$row->id}/lock")->assertForbidden();

        $this->assertSame('DRAFT', $row->fresh()->entry_status);
    }

    public function test_approver_can_approve_and_lock(): void
    {
        $manager = $this->userWithRole('project_manager');
        $row = $this->statusRow();

        $this->actingAs($manager)->post("/daily-statuses/{$row->id}/approve")->assertRedirect();
        $this->assertSame('APPROVED', $row->fresh()->entry_status);

        $this->actingAs($manager)->post("/daily-statuses/{$row->id}/lock")->assertRedirect();
        $this->assertSame('LOCKED', $row->fresh()->entry_status);
    }

    public function test_locked_row_rejects_edits_and_probe_writes(): void
    {
        $manager = $this->userWithRole('project_manager');
        $row = $this->statusRow('LOCKED');

        // The bound policy (renamed so auto-discovery finds it) must refuse.
        $this->assertFalse($manager->can('update', $row));

        $token = $manager->createToken('probe', ['heartbeat'])->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $this->site->ap_site_code,
            'status' => 'DOWN',
        ]);

        $response->assertStatus(409);
        $this->assertSame('UP', $row->fresh()->status);
    }

    public function test_approved_row_rejects_probe_writes(): void
    {
        $this->statusRow('APPROVED');

        $manager = $this->userWithRole('project_manager');
        $token = $manager->createToken('probe', ['heartbeat'])->plainTextToken;

        $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $this->site->ap_site_code,
            'status' => 'DOWN',
        ])->assertStatus(409);
    }

    public function test_approved_row_update_requires_project_approver(): void
    {
        $row = $this->statusRow('APPROVED');
        $ownApprover = $this->userWithRole('project_manager', $this->project->id);
        $otherProject = Project::create([
            'code' => 'OTHER', 'name' => 'Other Project', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $otherApprover = $this->userWithRole('project_manager', $otherProject->id);

        $this->assertTrue($ownApprover->can('update', $row));
        $this->assertFalse($otherApprover->can('update', $row));
    }

    public function test_heartbeat_token_needs_the_heartbeat_ability(): void
    {
        $manager = $this->userWithRole('project_manager');
        $token = $manager->createToken('read-only', ['other'])->plainTextToken;

        $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $this->site->ap_site_code,
            'status' => 'UP',
        ])->assertForbidden();
    }

    public function test_removed_resource_actions_are_not_routed(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get("/daily-statuses/{$this->site->id}")->assertNotFound();
        $this->actingAs($viewer)->put("/daily-statuses/{$this->site->id}", ['status' => 'UP'])->assertNotFound();
        $this->actingAs($viewer)->delete("/daily-statuses/{$this->site->id}")->assertNotFound();
        $this->actingAs($viewer)->put('/accomplishments/1', ['status' => 'UP'])->assertMethodNotAllowed();
        $this->actingAs($viewer)->delete('/accomplishments/1')->assertMethodNotAllowed();
    }

    public function test_probe_tokens_require_daily_create_permission(): void
    {
        $roleless = User::factory()->create();
        $this->actingAs($roleless)->post('/profile/probe-tokens', ['name' => 'field'])->assertForbidden();

        $encoder = $this->userWithRole('encoder');
        $this->actingAs($encoder)->post('/profile/probe-tokens', ['name' => 'field'])
            ->assertRedirect();
        $this->assertSame(1, $encoder->tokens()->count());
        $this->assertNotNull($encoder->tokens()->first()->expires_at);
    }

    public function test_heartbeat_token_is_scoped_to_the_owners_projects(): void
    {
        $otherProject = Project::create([
            'code' => 'OTHER', 'name' => 'Other Project', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $otherSite = Site::create([
            'project_id' => $otherProject->id, 'location_name' => 'Other Site',
            'ap_site_code' => 'AP-OTHER-1', 'latitude' => 18.0, 'longitude' => 121.0,
            'status' => 'active',
        ]);

        $encoder = $this->userWithRole('encoder', $this->project->id);
        $token = $encoder->createToken('probe', ['heartbeat'])->plainTextToken;

        // Own project: allowed.
        $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $this->site->ap_site_code, 'status' => 'UP',
        ])->assertOk();

        // Other project: forbidden, nothing written.
        $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $otherSite->ap_site_code, 'status' => 'DOWN',
        ])->assertForbidden();
        $this->assertSame(0, $otherSite->dailyStatuses()->count());
    }

    public function test_single_store_enforces_project_scope_and_workflow_guards(): void
    {
        $encoder = $this->userWithRole('encoder', $this->project->id);
        $otherProject = Project::create([
            'code' => 'OTHER', 'name' => 'Other Project', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $otherSite = Site::create([
            'project_id' => $otherProject->id, 'location_name' => 'Other Site',
            'latitude' => 18.0, 'longitude' => 121.0, 'status' => 'active',
        ]);

        // Own site, fresh date: creates a DRAFT row.
        $this->actingAs($encoder)->post('/daily-statuses', [
            'site_id' => $this->site->id, 'date' => today()->toDateString(), 'status' => 'DOWN',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('DRAFT', SiteDailyStatus::where('site_id', $this->site->id)
            ->whereDate('date', today())->first()->entry_status);

        // Other project: 403, nothing written.
        $this->actingAs($encoder)->post('/daily-statuses', [
            'site_id' => $otherSite->id, 'date' => today()->toDateString(), 'status' => 'UP',
        ])->assertForbidden();
        $this->assertSame(0, $otherSite->dailyStatuses()->count());

        // LOCKED row: rejected with an error, value untouched.
        $lockedDate = today()->subDay()->toDateString();
        SiteDailyStatus::create([
            'site_id' => $this->site->id, 'date' => $lockedDate, 'status' => 'UP',
            'entry_status' => 'LOCKED', 'created_by' => $encoder->id,
        ]);
        $this->actingAs($encoder)->post('/daily-statuses', [
            'site_id' => $this->site->id, 'date' => $lockedDate, 'status' => 'DOWN',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('UP', SiteDailyStatus::where('site_id', $this->site->id)
            ->whereDate('date', $lockedDate)->first()->status);

        // APPROVED row: encoder 403s, project approver may rewrite.
        $approvedDate = today()->subDays(2)->toDateString();
        SiteDailyStatus::create([
            'site_id' => $this->site->id, 'date' => $approvedDate, 'status' => 'UP',
            'entry_status' => 'APPROVED', 'created_by' => $encoder->id,
        ]);
        $this->actingAs($encoder)->post('/daily-statuses', [
            'site_id' => $this->site->id, 'date' => $approvedDate, 'status' => 'DOWN',
        ])->assertForbidden();

        $manager = $this->userWithRole('project_manager', $this->project->id);
        $this->actingAs($manager)->post('/daily-statuses', [
            'site_id' => $this->site->id, 'date' => $approvedDate, 'status' => 'DOWN',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('DOWN', SiteDailyStatus::where('site_id', $this->site->id)
            ->whereDate('date', $approvedDate)->first()->status);
    }

    public function test_batch_store_enforces_project_scope_and_locked_rows(): void
    {
        $encoder = $this->userWithRole('encoder', $this->project->id);
        $otherProject = Project::create([
            'code' => 'OTHER', 'name' => 'Other Project', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $otherSite = Site::create([
            'project_id' => $otherProject->id, 'location_name' => 'Other Site',
            'latitude' => 18.0, 'longitude' => 121.0, 'status' => 'active',
        ]);
        $locked = $this->statusRow('LOCKED');
        $date = today()->subDay()->toDateString();

        $this->actingAs($encoder)->post('/daily-statuses/batch', ['entries' => [
            ['site_id' => $this->site->id, 'date' => today()->toDateString(), 'status' => 'DOWN'],
            ['site_id' => $this->site->id, 'date' => $date, 'status' => 'UP'],
            ['site_id' => $otherSite->id, 'date' => $date, 'status' => 'UP'],
        ]])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('UP', $locked->fresh()->status);
        $this->assertSame('UP', SiteDailyStatus::where('site_id', $this->site->id)->whereDate('date', $date)->first()->status);
        $this->assertSame(0, $otherSite->dailyStatuses()->count());
    }

    public function test_read_routes_require_view_permissions(): void
    {
        $roleless = User::factory()->create();

        $this->actingAs($roleless)->get('/sites')->assertForbidden();
        $this->actingAs($roleless)->get("/sites/{$this->site->id}")->assertForbidden();
        $this->actingAs($roleless)->get('/daily-statuses')->assertForbidden();
        $this->actingAs($roleless)->get("/sites/{$this->site->id}/daily-grid")->assertForbidden();
        $this->actingAs($roleless)->get('/accomplishments')->assertForbidden();

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get('/sites')->assertOk();
        $this->actingAs($viewer)->get('/daily-statuses')->assertOk();
        $this->actingAs($viewer)->get('/accomplishments')->assertOk();
    }

    public function test_report_download_leaves_an_audit_row(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/x.pdf', 'pdf');
        $manager = $this->userWithRole('project_manager');

        $export = ReportExport::create([
            'user_id' => $manager->id,
            'type' => 'project',
            'params' => ['project_id' => $this->project->id],
            'status' => 'DONE',
            'filename' => 'reports/x.pdf',
            'download_name' => 'x.pdf',
        ]);

        $this->actingAs($manager)->get("/reports/exports/{$export->id}/download")->assertOk();

        $this->assertTrue(AuditLog::where('action', 'download report')
            ->where('auditable_id', $export->id)
            ->exists());
    }

    public function test_failed_import_marks_batch_failed_and_audits(): void
    {
        Event::fake(); // don't actually retry or dispatch anything

        $owner = User::factory()->create();
        $batch = FreewifiImportBatch::create([
            'filename' => 'workbook.xlsx',
            'imported_by' => $owner->id,
            'type' => 'sites',
            'job_status' => 'PROCESSING',
        ]);

        $job = new ProcessExcelImport($batch, storage_path('nonexistent.xlsx'), 'sites', 1);
        $job->failed(new \RuntimeException('boom'));

        $this->assertSame('FAILED', $batch->fresh()->job_status);
        $this->assertTrue(AuditLog::where('action', 'import failed (sites)')
            ->where('auditable_id', $batch->id)
            ->exists());
    }
}
