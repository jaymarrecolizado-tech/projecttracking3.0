<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectSitePagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private function project(): Project
    {
        return Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
    }

    private function site(Project $project): Site
    {
        return Site::create([
            'project_id' => $project->id,
            'location_name' => 'Tuguegarao City Hall',
            'ap_site_code' => 'AP-TUG-01',
            'municipality' => 'Tuguegarao City',
            'province' => 'Cagayan',
            'latitude' => 17.6132,
            'longitude' => 121.7270,
            'status' => 'active',
        ]);
    }

    public function test_projects_index_lists_projects(): void
    {
        $admin = $this->admin();
        $this->project();

        $this->actingAs($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Projects/Index')
                ->has('projects', 1)
                ->where('projects.0.code', 'FREEWIFI'));
    }

    public function test_project_show_renders_project_details(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $this->site($project);

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Projects/Show')
                ->where('project.name', 'Free WiFi for All')
                ->has('project.sites', 1));
    }

    public function test_sites_index_lists_sites_with_filters_object(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $site = $this->site($project);
        SiteDailyStatus::create([
            'site_id' => $site->id,
            'date' => today()->toDateString(),
            'status' => 'UP',
        ]);

        $this->actingAs($admin)->get(route('sites.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Index')
                ->has('sites.data', 1)
                ->where('sites.data.0.location_name', 'Tuguegarao City Hall')
                ->where('filters.search', null)
                ->has('projects', 1)
                ->has('provinces', 1));
    }

    public function test_site_show_renders_site_details(): void
    {
        $admin = $this->admin();
        $site = $this->site($this->project());

        $this->actingAs($admin)->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Show')
                ->where('site.location_name', 'Tuguegarao City Hall')
                ->has('site.active_deployments'));
    }

    public function test_project_sites_page_lists_that_projects_sites(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $this->site($project);

        $this->actingAs($admin)->get(route('projects.sites', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Index')
                ->where('project.id', $project->id)
                ->has('sites.data', 1));
    }
}
