<?php

namespace App\Http\Requests;

use App\Models\SiteSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a public survey submission against the questionnaire's own
 * definition — question keys and rating bounds come from the survey row, so a
 * changed question set cannot desync from validation.
 *
 * This request is reachable unauthenticated, so it must fail closed: unknown
 * keys are rejected rather than ignored, and rating values are bounded.
 */
class StoreSiteSurveyResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $survey = $this->survey();

        $ratingKeys = $survey?->ratingKeys() ?? [];
        $allKeys = $survey === null ? [] : collect($survey->questions)->pluck('key')->all();

        $rules = [
            // Honeypot: a real respondent never sees this field, so any value
            // in it marks a bot. `prohibited` fails the submission.
            'website' => 'prohibited',
            'elapsed_ms' => 'nullable|integer|min:0',
            'ratings' => 'required|array',
            // Comments live at the top level; the rating bag is closed.
            'ratings.*' => 'nullable|integer|between:1,5',
            'comments' => 'nullable|string|max:2000',
        ];

        foreach ($ratingKeys as $key) {
            $rules["ratings.{$key}"] = ['required', 'integer', 'between:1,5'];
        }

        // Reject anything that is not a known question key, so a crafted body
        // cannot smuggle extra data into the JSON column. `Rule::array` with an
        // explicit key list fails closed when a key is unknown.
        if ($allKeys !== []) {
            $rules['ratings'] = ['required', 'array', Rule::array($allKeys)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'Submission rejected.',
            'ratings.required' => 'Please answer the questions before submitting.',
            'ratings.array' => 'Submission rejected.',
        ];
    }

    protected function survey(): ?SiteSurvey
    {
        return $this->route('survey') instanceof SiteSurvey
            ? $this->route('survey')
            : SiteSurvey::active();
    }

    /**
     * The rating answers, keyed by question, with nulls for unanswered
     * optional questions.
     *
     * @return array<string, mixed>
     */
    public function ratings(): array
    {
        $ratings = $this->validated('ratings');
        if (! is_array($ratings)) {
            return [];
        }

        return array_filter($ratings, fn ($value) => $value !== null);
    }
}
