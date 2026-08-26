<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:update) enforces permission.
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
