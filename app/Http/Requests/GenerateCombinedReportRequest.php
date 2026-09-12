<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * The report-builder pack: shared scope plus at least one analytic section.
 */
class GenerateCombinedReportRequest extends GenerateScopedReportRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'sections' => 'required|array|min:1',
            'sections.*' => Rule::in(['ops_period', 'fleet', 'incidents', 'progress']),
        ];
    }
}
