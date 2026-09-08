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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('name', $role)->value('id'),
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

    public function test_heartbeat_token_needs_the_heartbeat_ability(): void
    {
        $manager = $this->userWithRole('project_manager');
        $token = $manager->createToken('read-only', ['other'])->plainTextToken;

        $this->withToken($token)->postJson('/api/heartbeat', [
            'site_code' => $this->site->ap_site_code,
            'status' => 'UP',
        ])->assertForbidden();
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
