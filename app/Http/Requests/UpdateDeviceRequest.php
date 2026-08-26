<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_model_id' => 'required|exists:device_models,id',
            'asset_tag' => ['required', 'string', 'max:100', Rule::unique('devices', 'asset_tag')->ignore($this->route('device'))],
            'serial_number' => ['required', 'string', 'max:255', Rule::unique('devices', 'serial_number')->ignore($this->route('device'))],
            'mac_address' => ['nullable', 'mac_address', Rule::unique('devices', 'mac_address')->ignore($this->route('device'))],
            'firmware_version' => 'nullable|string|max:100',
            'status' => 'required|in:in_stock,deployed,under_repair,retired,lost',
            'condition' => 'required|in:new,good,degraded,faulty',
            'purchase_order_no' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:150',
            'unit_cost' => 'nullable|numeric|min:0',
            'purchased_at' => 'nullable|date',
            'warranty_until' => 'nullable|date',
            'notes' => 'nullable|string',
            // reassignment
            'site_id' => ['nullable', Rule::requiredIf($this->input('status') === 'deployed'), 'exists:sites,id'],
            'role_at_site' => 'nullable|in:primary_ap,backup_ap,backhaul,power,surveillance,other',
            'installed_at' => 'nullable|date',
        ];
    }
}
