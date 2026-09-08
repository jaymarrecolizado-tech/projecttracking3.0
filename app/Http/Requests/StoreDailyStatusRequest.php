<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'required|exists:sites,id',
            'date' => 'required|date',
            // Was in:UP,DOWN,NO_DATA, so NO_NMS and DOWN_SERVER — both real
            // states the NMS reports — could never be entered by hand.
            'status' => ['required', Rule::in(config('daily_status.codes'))],
            'total_unique_users' => 'nullable|integer|min:0',
            'bandwidth_utilization_mbps' => 'nullable|numeric|min:0',
            'uptime_percent' => 'nullable|numeric|between:0,100',
            'notes' => 'nullable|string',
            // entry_status is deliberately NOT client-writable: accepting it
            // let any daily.edit user self-approve or permanently lock a row
            // (Plan_revision §Phase 2.2). Use the approve/lock endpoints.
        ];
    }
}
