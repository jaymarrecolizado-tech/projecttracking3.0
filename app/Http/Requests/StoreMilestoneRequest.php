<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:milestone.manage) enforces permission.
    }

    public function rules(): array
    {
        return [
            'milestone_name' => 'required|string|max:200',
            'milestone_order' => 'required|integer|min:0',
            'weight_pct' => 'required|numeric|between:0,100',
            'description' => 'nullable|string',
        ];
    }
}
