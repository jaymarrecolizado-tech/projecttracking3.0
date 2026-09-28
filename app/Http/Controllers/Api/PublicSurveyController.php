<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteSurveyResponseRequest;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use App\Services\SiteSurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * JSON face of the public survey, for the Next.js public surface
 * (Plan_UI.md Part II §9).
 *
 * The Inertia `SiteSurveyController` remains the system of record's HTML face and
 * is deliberately left untouched — it is the rollback path, and the one that keeps
 * working if the Node process is down.
 *
 * The split is deliberate and narrow. **Laravel decides, Next.js renders.** Every
 * anti-abuse property stays on this side:
 *
 *  - the POST is `signed` and `throttle:survey`, so a signature cannot be replayed
 *    against another site and the limiter still keys on the real client IP;
 *  - the honeypot and the 3-second minimum are enforced in
 *    `StoreSiteSurveyResponseRequest` / below, not in the frontend;
 *  - `ip_hash` is derived here from `$request->ip()`. The browser POSTs to this
 *    endpoint **directly** rather than through a Next.js proxy — a proxied POST
 *    would hash the Node server's IP and collapse every respondent into a single
 *    duplicate, silently breaking both the rate limit and duplicate suppression.
 *
 * Nothing here is authenticated. It returns no more than the rendered page already
 * exposes, and an unknown site code is a neutral 404, not a disclosure.
 */
class PublicSurveyController extends Controller
{
    /** Minimum time on the form before a submission counts as human. */
    private const MIN_ELAPSED_MS = 3000;

    public function __construct(private readonly SiteSurveyService $surveys) {}

    /**
     * The questionnaire for one site, plus a signed submit URL.
     *
     * The signature is minted here and handed to the browser verbatim, so the URL
     * the browser signs up to is exactly the one it will POST to.
     */
    public function show(string $siteCode): JsonResponse
    {
        $site = $this->surveys->resolveSite($siteCode);

        // A stale QR or someone probing. Neutral 404, no detail leaked.
        if ($site === null) {
            return response()->json(['status' => 'unknown'], 404);
        }

        $survey = SiteSurvey::active();

        if ($survey === null) {
            return response()->json(['status' => 'unavailable'], 409);
        }

        // Mint the signature against the *public* root, not the host this request
        // happened to arrive on. The Next.js server calls this endpoint
        // server-side, which in a local/dev topology may arrive as
        // `http://127.0.0.1:8000`; a signature computed for that host would not
        // validate when the browser POSTs to the real domain, and every
        // submission would fail for a reason invisible from the server side.
        URL::forceRootUrl(config('app.url'));

        return response()->json([
            'status' => 'ok',
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
            'submitUrl' => URL::signedRoute('api.public.surveys.store', ['siteCode' => $siteCode]),
            'startedAt' => now()->getTimestampMs(),
        ]);
    }

    /**
     * Record a submission.
     *
     * Failures a respondent can cause never surface as a 500 — a stranger on a
     * captive portal must not see one. The too-fast case answers `ok` rather than
     * an error, matching the HTML face: signalling *why* would hand a bot a probe
     * loop, and the record is simply not written.
     */
    public function store(StoreSiteSurveyResponseRequest $request, string $siteCode): JsonResponse
    {
        $site = $this->surveys->resolveSite($siteCode);
        $survey = SiteSurvey::active();

        if ($site === null || $survey === null) {
            return response()->json(['status' => 'unavailable'], 409);
        }

        $elapsed = (int) $request->input('elapsed_ms', 0);

        if ($elapsed <= 0 || $elapsed >= self::MIN_ELAPSED_MS) {
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
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * The acknowledgement page's only data point.
     *
     * Split out so the thanks screen does not have to re-fetch the whole
     * questionnaire, and so it stays renderable for an unknown code.
     */
    public function thanks(Request $request, string $siteCode): JsonResponse
    {
        $site = $this->surveys->resolveSite($siteCode);

        return response()->json([
            'status' => 'ok',
            'siteName' => $site?->location_name,
        ]);
    }
}
