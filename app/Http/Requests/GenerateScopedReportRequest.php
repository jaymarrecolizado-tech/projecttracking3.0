<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared scope for the analytics PDF family: period (default last 7 days)
 * plus project/geo filters. Every field is optional so the four existing
 * endpoints keep accepting exactly what their forms post today.
 */
class GenerateScopedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:reports.export) enforces permission.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'project_id' => 'nullable|integer|exists:projects,id',
            'province' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
            'site_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ];
    }

    /**
     * Non-empty scope values for analytics + params persistence.
     *
     * @return array<string, mixed>
     */
    public function scope(): array
    {
        return collect($this->validated())->filter()->all();
    }
}
