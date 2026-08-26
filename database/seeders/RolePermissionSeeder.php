<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roles = [
                'admin' => 'Full system access',
                'project_manager' => 'CRUD for assigned project sites',
                'encoder' => 'Submit daily logs or accomplishments for assigned project',
                'viewer' => 'Read-only access to dashboards and maps',
                'auditor' => 'Read + audit log access',
            ];
            foreach ($roles as $name => $description) {
                DB::table('roles')->updateOrInsert(['name' => $name], ['description' => $description]);
            }

            $permissions = [
                'sites.create', 'sites.view', 'sites.edit', 'sites.delete',
                'devices.view', 'devices.create', 'devices.edit', 'devices.delete',
                'daily.create', 'daily.view', 'daily.edit', 'daily.submit', 'daily.approve',
                'accomplishment.create', 'accomplishment.view', 'accomplishment.edit', 'accomplishment.submit',
                'milestone.manage',
                'import.excel',
                'reports.view', 'reports.export',
                'tickets.manage',
                'users.manage',
                'audit.view',
                'projects.manage',
            ];
            foreach ($permissions as $perm) {
                DB::table('permissions')->updateOrInsert(['name' => $perm]);
            }

            // Rebuild the matrix from scratch so re-seeding never duplicates pivots.
            DB::table('role_permission')->delete();

            $roleId = fn (string $name) => DB::table('roles')->where('name', $name)->value('id');

            $matrix = [
                'admin' => $permissions,
                'project_manager' => ['sites.create', 'sites.view', 'sites.edit', 'devices.view', 'devices.create', 'devices.edit', 'daily.create', 'daily.view', 'daily.edit', 'daily.submit', 'daily.approve', 'accomplishment.create', 'accomplishment.view', 'accomplishment.edit', 'accomplishment.submit', 'milestone.manage', 'reports.view', 'reports.export', 'import.excel'],
                'encoder' => ['sites.view', 'devices.view', 'daily.create', 'daily.view', 'daily.edit', 'daily.submit', 'accomplishment.create', 'accomplishment.view', 'accomplishment.edit', 'accomplishment.submit'],
                'viewer' => ['sites.view', 'devices.view', 'daily.view', 'accomplishment.view', 'reports.view'],
                'auditor' => ['sites.view', 'devices.view', 'daily.view', 'accomplishment.view', 'reports.view', 'reports.export', 'audit.view'],
            ];

            foreach ($matrix as $roleName => $rolePerms) {
                $rid = $roleId($roleName);
                foreach (DB::table('permissions')->whereIn('name', $rolePerms)->pluck('id') as $pid) {
                    DB::table('role_permission')->insertOrIgnore(['role_id' => $rid, 'permission_id' => $pid]);
                }
            }
        });
    }
}
