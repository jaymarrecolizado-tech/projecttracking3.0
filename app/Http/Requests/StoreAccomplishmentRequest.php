<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreAccomplishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'site_id' => 'required|exists:sites,id',
            'milestone_id' => 'required|exists:project_milestones,id',
            'status' => 'required|in:NOT_STARTED,IN_PROGRESS,COMPLETED,ON_HOLD,CANCELLED',
            'pct_complete' => 'required|numeric|between:0,100',
            'target_date' => 'nullable|date',
            'actual_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'attachment_path' => 'nullable|string|max:255',
        ];
    }
}
