<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:users.manage) enforces permission.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => 'nullable|string|min:8',
            'is_active' => 'required|boolean',
            'roles' => 'nullable|array',
            'roles.*.role_id' => 'required|integer|exists:roles,id',
            'roles.*.project_id' => 'nullable|integer|exists:projects,id',
        ];
    }

    /** Admins cannot deactivate or demote themselves — lockout protection. */
    /** @return array<string, mixed> */
    public function withSelfProtection(User $target): array
    {
        $data = $this->validated();

        if ($this->user()->id === $target->id) {
            $data['is_active'] = true;
            // Role changes to your own account must come from another admin.
            $data['roles'] = $this->currentAssignments($target);

            return $data;
        }

        // Nor may anyone deactivate or demote the last active administrator.
        if ($target->is_active && $target->hasPermission('users.manage') && User::activeAdminCount() <= 1) {
            $data['is_active'] = true;
            $data['roles'] = $this->currentAssignments($target);
        }

        return $data;
    }

    /** @return list<array{role_id: mixed, project_id: mixed}> */
    private function currentAssignments(User $target): array
    {
        return $target->roles->map(fn ($role) => [
            'role_id' => $role->id,
            'project_id' => data_get($role, 'pivot.project_id'),
        ])->all();
    }
}
