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
    public function withSelfProtection(User $target): array
    {
        $data = $this->validated();

        if ($this->user()->id === $target->id) {
            $data['is_active'] = true;
        }

        return $data;
    }
}
