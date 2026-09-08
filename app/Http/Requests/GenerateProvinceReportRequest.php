<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProvinceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by the can:reports.export middleware on the route.
    }

    public function rules(): array
    {
        return [
            'province' => 'required|string',
            'project_id' => 'nullable|exists:projects,id',
        ];
    }
}
