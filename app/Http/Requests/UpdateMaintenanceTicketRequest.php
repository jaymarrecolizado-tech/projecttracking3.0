<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:tickets.manage) enforces permission.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => 'sometimes|required|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('roles.permissions', fn ($p) => $p->where('permissions.name', 'tickets.manage')))],
            'resolution_notes' => 'nullable|string|required_if:status,RESOLVED',
        ];
    }

    /** @return array<string, mixed> */
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
