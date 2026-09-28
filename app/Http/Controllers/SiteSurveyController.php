<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteSurveyResponseRequest;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use App\Services\SiteSurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public survey surface (Plan.md S1–S5) — the only unauthenticated page in
 * the app.
 *
 * Reached from a signed link minted for one site (QR code at the site, or the
 * AP-vendor guest portal's redirect URL). The site code in the URL is never
 * trusted as the answer: it is resolved server-side through SiteCodeResolver,
 * which also follows a merged duplicate to its canonical site.
 */
class SiteSurveyController extends Controller
{
    /** Minimum time on the form before a submission counts as human. */
    private const MIN_ELAPSED_MS = 3000;

    public function __construct(private readonly SiteSurveyService $surveys) {}

    public function show(Request $request, string $siteCode): Response
    {
        $site = $this->surveys->resolveSite($siteCode);

        // A stale QR or someone probing. Neutral page, no detail leaked.
        if ($site === null) {
            return Inertia::render('Survey/Unknown');
        }

        $survey = SiteSurvey::active();

        if ($survey === null) {
            return Inertia::render('Survey/Unavailable');
        }

        return Inertia::render('Survey/Form', [
            'site' => [
                'name' => $site->location_name,
                'barangay' => $site->barangay,
                'municipality' => $site->municipality,
                'province' => $site->province,
            ],
            'survey' => [
                'title' => $survey->title,
                'questions' => $survey->questions,
            ],
            // Signed so the POST cannot be replayed against another site.
            'submitUrl' => URL::signedRoute('survey.store', ['siteCode' => $siteCode]),
            'startedAt' => now()->getTimestampMs(),
        ]);
    }

    public function store(StoreSiteSurveyResponseRequest $request, string $siteCode): RedirectResponse
    {
        $site = $this->surveys->resolveSite($siteCode);

        if ($site === null) {
            return redirect()->route('survey.show', ['siteCode' => $siteCode]);
        }

        $survey = SiteSurvey::active();

        if ($survey === null) {
            return redirect()->route('survey.show', ['siteCode' => $siteCode]);
        }

        // Too fast to be a human reading the questions. Treated as a bot and
        // silently accepted-looking (no signal about why) to avoid a probing loop.
        $elapsed = (int) $request->input('elapsed_ms', 0);
        if ($elapsed > 0 && $elapsed < self::MIN_ELAPSED_MS) {
            return redirect()->route('survey.thanks', ['siteCode' => $siteCode]);
        }

        $ipHash = SiteSurveyResponse::hashIp($request->ip());
        $ratings = $request->ratings();
        $comments = $request->validated('comments');

        // Duplicate suppression is soft: a returning respondent updates their
        // own answers rather than inflating the site's average.
        $existing = $this->surveys->existingResponse($site, $ipHash);

        if ($existing !== null) {
            $this->surveys->replace($existing, $ratings, $comments);
        } else {
            $this->surveys->record($site, $survey, $ratings, $comments, $ipHash, $request->userAgent());
        }

        return redirect()->route('survey.thanks', ['siteCode' => $siteCode]);
    }

    public function thanks(string $siteCode): Response
    {
        $site = $this->surveys->resolveSite($siteCode);

        return Inertia::render('Survey/Thanks', [
            'siteName' => $site?->location_name,
        ]);
    }
}
