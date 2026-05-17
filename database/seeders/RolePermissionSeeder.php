<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['name' => 'admin', 'description' => 'Full system access'],
            ['name' => 'project_manager', 'description' => 'CRUD for assigned project sites'],
            ['name' => 'encoder', 'description' => 'Submit daily logs or accomplishments for assigned project'],
            ['name' => 'viewer', 'description' => 'Read-only access to dashboards and maps'],
            ['name' => 'auditor', 'description' => 'Read + audit log access'],
        ]);
        $permissions = [
            'sites.create','sites.view','sites.edit','sites.delete',
            'daily.create','daily.view','daily.edit','daily.submit','daily.approve',
            'accomplishment.create','accomplishment.view','accomplishment.edit','accomplishment.submit',
            'milestone.manage',
            'import.excel',
            'reports.view','reports.export',
            'users.manage',
            'audit.view',
            'projects.manage',
        ];
        foreach ($permissions as $perm) {
            DB::table('permissions')->insert(['name' => $perm]);
        }
        $adminRole = DB::table('roles')->where('name', 'admin')->first()->id;
        $allPerms = DB::table('permissions')->pluck('id');
        foreach ($allPerms as $permId) {
            DB::table('role_permission')->insert(['role_id' => $adminRole, 'permission_id' => $permId]);
        }
        $managerRole = DB::table('roles')->where('name', 'project_manager')->first()->id;
        $managerPerms = ['sites.create','sites.view','sites.edit','daily.create','daily.view','daily.edit','daily.submit','daily.approve','accomplishment.create','accomplishment.view','accomplishment.edit','accomplishment.submit','milestone.manage','reports.view','reports.export','import.excel'];
        foreach (DB::table('permissions')->whereIn('name', $managerPerms)->get() as $perm) {
            DB::table('role_permission')->insert(['role_id' => $managerRole, 'permission_id' => $perm->id]);
        }
        $encoderRole = DB::table('roles')->where('name', 'encoder')->first()->id;
        $encoderPerms = ['sites.view','daily.create','daily.view','daily.edit','daily.submit','accomplishment.create','accomplishment.view','accomplishment.edit','accomplishment.submit'];
        foreach (DB::table('permissions')->whereIn('name', $encoderPerms)->get() as $perm) {
            DB::table('role_permission')->insert(['role_id' => $encoderRole, 'permission_id' => $perm->id]);
        }
        $viewerRole = DB::table('roles')->where('name', 'viewer')->first()->id;
        $viewerPerms = ['sites.view','daily.view','accomplishment.view','reports.view'];
        foreach (DB::table('permissions')->whereIn('name', $viewerPerms)->get() as $perm) {
            DB::table('role_permission')->insert(['role_id' => $viewerRole, 'permission_id' => $perm->id]);
        }
        $auditorRole = DB::table('roles')->where('name', 'auditor')->first()->id;
        $auditorPerms = ['sites.view','daily.view','accomplishment.view','reports.view','reports.export','audit.view'];
        foreach (DB::table('permissions')->whereIn('name', $auditorPerms)->get() as $perm) {
            DB::table('role_permission')->insert(['role_id' => $auditorRole, 'permission_id' => $perm->id]);
        }
    }
}
