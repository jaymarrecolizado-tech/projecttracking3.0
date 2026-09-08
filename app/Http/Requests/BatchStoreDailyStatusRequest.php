<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchStoreDailyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:daily.create) enforces permission.
    }

    public function rules(): array
    {
        return [
            'entries' => 'required|array',
            'entries.*.site_id' => 'required|exists:sites,id',
            'entries.*.date' => 'required|date',
            'entries.*.status' => ['required', Rule::in(config('daily_status.codes'))],
            'entries.*.total_unique_users' => 'nullable|integer|min:0',
            'entries.*.bandwidth_utilization_mbps' => 'nullable|numeric|min:0',
            'entries.*.uptime_percent' => 'nullable|numeric|between:0,100',
        ];
    }
}
