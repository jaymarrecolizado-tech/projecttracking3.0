<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:tickets.manage) enforces permission.
    }

    public function rules(): array
    {
        return [
            'site_id' => 'nullable|exists:sites,id',
            'device_id' => 'nullable|exists:devices,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'category' => 'required|in:connectivity,hardware,power,firmware,other',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }
}
