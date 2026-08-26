<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDailyOpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Per-entry permission checks happen in the controller.
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date|before_or_equal:today',
            'action' => 'required|in:save_draft,submit,approve',
            'entries' => 'required|array|min:1',
            'entries.*.site_id' => 'required|integer|exists:sites,id',
            'entries.*.status' => 'required|in:UP,DOWN,NO_NMS',
            'entries.*.bandwidth_utilization_mbps' => 'nullable|numeric|min:0',
            'entries.*.total_unique_users' => 'nullable|integer|min:0',
            'entries.*.remarks' => 'nullable|string|max:1000',
        ];
    }
}
