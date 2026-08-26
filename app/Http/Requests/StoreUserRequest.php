<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:users.manage) enforces permission.
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'nullable|array',
            'roles.*.role_id' => 'required|integer|exists:roles,id',
            'roles.*.project_id' => 'nullable|integer|exists:projects,id',
        ];
    }
}
