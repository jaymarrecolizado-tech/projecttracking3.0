<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware (can:import.excel) enforces permission.
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'type' => 'nullable|in:sites,devices',
        ];
    }
}
