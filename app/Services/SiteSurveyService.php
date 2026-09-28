<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;

/**
 * The single write path for public survey submissions (Plan.md S4).
 *
 * Deliberately thin and total: it either records a response or explains why
 * not, and it never throws for anything a respondent can cause. A stranger on a
 * captive portal must never see a 500 because of our own logic.
 */
class SiteSurveyService
{
    /** One response per (site, ip_hash) inside this window may be recorded. */
    public const DUPLICATE_WINDOW_HOURS = 24;

    public function __construct(private readonly SiteCodeResolver $resolver) {}

    public function resolveSite(string $siteCode): ?Site
    {
        return $this->resolver->resolve($siteCode);
    }

    /**
     * A respondent at this site within the duplicate window, if any.
     *
     * Used for suppression rather than hard rejection: a user who reconnects
     * and answers again replaces their earlier answers instead of inflating
     * the average.
     */
    public function existingResponse(Site $site, ?string $ipHash): ?SiteSurveyResponse
    {
        if ($ipHash === null) {
            return null;
        }

        return SiteSurveyResponse::where('site_id', $site->id)
            ->where('ip_hash', $ipHash)
            ->where('submitted_at', '>=', now()->subHours(self::DUPLICATE_WINDOW_HOURS))
            ->latest('submitted_at')
            ->first();
    }

    /**
     * Record a submission. Provider and transport are captured here, at submit
     * time, so a later re-assignment of the site does not rewrite the provider
     * history that reports were built on.
     *
     * @param  array<string, mixed>  $ratings
     */
    public function record(
        Site $site,
        SiteSurvey $survey,
        array $ratings,
        ?string $comments,
        ?string $ipHash,
        ?string $userAgent,
    ): SiteSurveyResponse {
        return SiteSurveyResponse::create([
            'site_id' => $site->id,
            'survey_id' => $survey->id,
            'ap_site_code' => $site->ap_site_code,
            'cms_provider' => $site->cms_provider ?? $site->isp_provider,
            'last_mile_tech' => $site->last_mile_tech,
            'ratings' => $ratings,
            'comments' => $comments,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Replace a previous response's answers rather than adding a second row.
     *
     * @param  array<string, mixed>  $ratings
     */
    public function replace(SiteSurveyResponse $response, array $ratings, ?string $comments): SiteSurveyResponse
    {
        $response->update([
            'ratings' => $ratings,
            'comments' => $comments,
            'submitted_at' => now(),
        ]);

        return $response;
    }
}
