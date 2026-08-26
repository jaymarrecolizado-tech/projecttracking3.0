<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:create) enforces permission.
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:projects',
            'name' => 'required|string|max:150',
            'report_type' => 'required|in:freewifi,milestone',
            'marker_color' => 'required|string|max:7',
            'marker_shape' => 'required|in:circle,square,diamond,hexagon,star',
            'marker_icon' => 'required|string|max:50',
        ];
    }
}
