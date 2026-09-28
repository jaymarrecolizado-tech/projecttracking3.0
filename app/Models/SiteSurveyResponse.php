<?php

namespace App\Models;

use Database\Factories\SiteSurveyResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One survey submission. Holds no personally identifying information — the
 * respondent is anonymous by design; only the site they were connected to is
 * recorded (Plan.md S3/S5).
 */
class SiteSurveyResponse extends Model
{
    /** @use HasFactory<SiteSurveyResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id', 'survey_id', 'ap_site_code', 'cms_provider', 'last_mile_tech',
        'ratings', 'comments', 'ip_hash', 'user_agent', 'submitted_at',
    ];

    protected $casts = [
        'ratings' => 'array',
        'submitted_at' => 'datetime',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<SiteSurvey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class, 'survey_id');
    }

    /** @param Builder<self> $query */
    public function scopeSince(Builder $query, \DateTimeInterface $since): void
    {
        $query->where('submitted_at', '>=', $since);
    }

    /**
     * Stable, non-reversible respondent fingerprint used for duplicate
     * suppression. Never stores the address itself.
     */
    public static function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash('sha256', $ip.config('app.key'));
    }
}
