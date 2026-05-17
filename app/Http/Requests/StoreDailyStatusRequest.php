<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
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
            'status' => 'required|in:UP,DOWN,NO_DATA',
            'total_unique_users' => 'nullable|integer|min:0',
            'bandwidth_utilization_mbps' => 'nullable|numeric|min:0',
            'uptime_percent' => 'nullable|numeric|between:0,100',
            'notes' => 'nullable|string',
            'entry_status' => 'sometimes|in:DRAFT,SUBMITTED,APPROVED,LOCKED',
        ];
    }
}
