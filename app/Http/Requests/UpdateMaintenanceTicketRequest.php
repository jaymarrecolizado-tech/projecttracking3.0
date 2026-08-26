<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:tickets.manage) enforces permission.
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|required|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string|required_if:status,RESOLVED',
        ];
    }

    public function validatedWithTimestamps(): array
    {
        $data = $this->validated();
        if (($data['status'] ?? null) === 'RESOLVED' && ! isset($data['resolved_at'])) {
            $data['resolved_at'] = now();
        }
        if (in_array($data['status'] ?? null, ['OPEN', 'IN_PROGRESS'], true)) {
            $data['resolved_at'] = null;
        }

        return $data;
    }
}
