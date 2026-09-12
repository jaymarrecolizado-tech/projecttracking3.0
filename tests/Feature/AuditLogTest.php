<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_fields_are_redacted_and_payload_is_capped(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
        ]);

        $payload = [
            'location_name' => 'Audit Site',
            'latitude' => 14.6,
            'longitude' => 120.9,
            // Credential-shaped keys must never land in the audit trail verbatim.
            'password' => 'super-secret-value',
            'api_secret' => 'leak-me-not',
        ];
        for ($i = 0; $i < 60; $i++) {
            $payload["filler_{$i}"] = "value {$i}";
        }

        $this->actingAs($admin)->post(route('sites.store'), $payload)->assertRedirect();

        $log = AuditLog::where('user_id', $admin->id)->latest('id')->first();
        $this->assertNotNull($log, 'Expected an audit row for the mutating request.');

        $newValues = $log->new_values;
        // Top-level credential keys are stripped entirely; nested ones redacted.
        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertSame('[REDACTED]', $newValues['api_secret']);

        // Payload hard cap keeps bulk submissions from bloating rows
        // (40 user keys + the request_id metadata key).
        $this->assertCount(41, $newValues);
        $this->assertArrayHasKey('request_id', $newValues);
        $logged = json_encode($newValues);
        $this->assertStringNotContainsString('super-secret-value', $logged);
        $this->assertStringNotContainsString('leak-me-not', $logged);
    }

    public function test_http_and_observer_rows_share_a_request_id(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_icon' => 'wifi',
        ]);

        $this->actingAs($admin)->post(route('sites.store'), [
            'project_id' => $project->id,
            'location_name' => 'Grouped Site',
            'latitude' => 14.6,
            'longitude' => 120.9,
            'status' => 'planned',
        ])->assertRedirect();

        $http = AuditLog::where('auditable_type', 'general')->latest('id')->firstOrFail();
        $model = AuditLog::where('auditable_type', Site::class)->latest('id')->firstOrFail();
        $this->assertSame($http->new_values['request_id'], $model->new_values['request_id']);
    }

    public function test_audit_prune_deletes_only_rows_past_retention(): void
    {
        $old = AuditLog::create([
            'user_id' => null, 'action' => 'old', 'auditable_type' => 'general',
            'auditable_id' => null,
        ]);
        $old->created_at = now()->subDays(100);
        $old->save();
        AuditLog::create([
            'user_id' => null, 'action' => 'fresh', 'auditable_type' => 'general',
            'auditable_id' => null,
        ]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'old']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fresh']);
    }

    public function test_auth_routes_are_not_audited(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertRedirect();

        $this->assertSame(0, AuditLog::where('action', 'POST login')->count());
    }
}
