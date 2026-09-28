<?php

namespace App\Models;

use Database\Factories\SiteSurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A survey questionnaire. Questions live in a JSON column so the question set
 * is data — a new version is a seeded row, not a migration.
 *
 * Shape of `questions`:
 *   [{ "key": "overall", "label": "Overall, how was the connection?",
 *      "type": "rating", "required": true }, …]
 * `type` is one of rating | choice | text.
 */
class SiteSurvey extends Model
{
    /** @use HasFactory<SiteSurveyFactory> */
    use HasFactory;

    protected $fillable = ['code', 'title', 'questions', 'is_active'];

    protected $casts = [
        'questions' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<SiteSurveyResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SiteSurveyResponse::class, 'survey_id');
    }

    /** The active questionnaire, or null when none is configured. */
    public static function active(): ?self
    {
        return static::where('is_active', true)->orderByDesc('id')->first();
    }

    /**
     * The public URL a respondent at this site should be sent to, or null when
     * no survey is published. One source, because the same link is printed on
     * a QR placard, shown on Site Show and handed to an AP-vendor portal — if
     * those ever disagree, responses land against the wrong site.
     *
     * Deliberately unsigned: survey.show carries no `signed` middleware (a
     * mistyped link must fail to a neutral page, not a 403), so a signature
     * would only lengthen the QR and expire against the wrong host. The POST
     * is signed at render time, which is the half that actually needs it.
     */
    public static function urlFor(?string $siteCode): ?string
    {
        if ($siteCode === null || $siteCode === '' || static::active() === null) {
            return null;
        }

        return route('survey.show', ['siteCode' => $siteCode]);
    }

    /**
     * Question keys that hold a numeric rating — the ones aggregation averages.
     *
     * @return list<string>
     */
    public function ratingKeys(): array
    {
        return collect($this->questions ?? [])
            ->where('type', 'rating')
            ->pluck('key')
            ->values()
            ->all();
    }
}
