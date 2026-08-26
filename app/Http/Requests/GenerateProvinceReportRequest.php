<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProvinceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Reports index is behind auth; export permission checked at download.
    }

    public function rules(): array
    {
        return [
            'province' => 'required|string',
            'project_id' => 'nullable|exists:projects,id',
        ];
    }
}
