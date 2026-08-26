<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_model_id' => 'required|exists:device_models,id',
            'asset_tag' => 'required|string|max:100|unique:devices,asset_tag',
            'serial_number' => 'required|string|max:255|unique:devices,serial_number',
            'mac_address' => 'nullable|mac_address|unique:devices,mac_address',
            'firmware_version' => 'nullable|string|max:100',
            'status' => 'required|in:in_stock,deployed,under_repair,retired,lost',
            'condition' => 'required|in:new,good,degraded,faulty',
            'purchase_order_no' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:150',
            'unit_cost' => 'nullable|numeric|min:0',
            'purchased_at' => 'nullable|date',
            'warranty_until' => 'nullable|date',
            'notes' => 'nullable|string',
            // initial deployment when registered straight into a site
            'site_id' => ['nullable', 'required_if:status,deployed', 'exists:sites,id'],
            'role_at_site' => 'nullable|in:primary_ap,backup_ap,backhaul,power,surveillance,other',
            'installed_at' => 'nullable|date',
        ];
    }
}
