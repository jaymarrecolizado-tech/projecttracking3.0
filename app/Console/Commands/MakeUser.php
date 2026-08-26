<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeUser extends Command
{
    protected $signature = 'user:make {--role=admin : Role name} {--project= : Project code to scope the role to (optional)}';

    protected $description = 'Create a user (name, email, password) with a role';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $password = $this->secret('Password (min 8 characters)');
        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $role = Role::where('name', $this->option('role'))->first();
        if (! $role) {
            $this->error("Role '{$this->option('role')}' not found. Available: ".Role::pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $projectId = null;
        if ($projectCode = $this->option('project')) {
            $projectId = Project::where('code', strtoupper($projectCode))->value('id');
            if (! $projectId) {
                $this->error("Project '{$projectCode}' not found.");

                return self::FAILURE;
            }
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, ['project_id' => $projectId]);

        $scope = $projectId ? " (scoped to project ID {$projectId})" : ' (global)';
        $this->info("Created {$email} with role '{$role->name}'{$scope}.");

        return self::SUCCESS;
    }
}
