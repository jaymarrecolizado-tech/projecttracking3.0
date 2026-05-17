<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'nationwide_id' => 'nullable|string|max:50',
            'ap_site_code' => 'nullable|string|max:50',
            'location_name' => 'required|string|max:255',
            'ap_site_name' => 'nullable|string|max:255',
            'site_type' => 'nullable|string|max:80',
            'barangay' => 'nullable|string|max:150',
            'municipality' => 'nullable|string|max:150',
            'province' => 'nullable|string|max:150',
            'district' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'island_group' => 'nullable|in:Luzon,Visayas,Mindanao',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'date_of_activation' => 'nullable|date',
            'status' => 'required|in:planned,active,inactive,decommissioned,maintenance',
            'isp_provider' => 'nullable|string|max:100',
            'last_mile_tech' => 'nullable|string|max:80',
            'bw_download_cir' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|json',
        ];
    }
}
