<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteSurveyResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-site satisfaction rollups (Plan.md S6).
 *
 * Deliberately kept out of `uptimePct()` and `config/daily_status.php`:
 * satisfaction is a second, independent dimension beside availability, never
 * folded into it. A site can be UP and still be unusable, which is exactly the
 * signal this exists to expose.
 *
 * @phpstan-type Summary array{
 *   responses: int, meets_minimum: bool, overall: float|null,
 *   by_question: array<string, float|null>, distribution: array<int, int>,
 *   response_rate: float|null
 * }
 * @phpstan-type ProviderRow array{cms_provider: string|null, last_mile_tech: string|null, responses: int, meets_minimum: bool, overall: float|null}
 */
class SiteSurveyAnalytics
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /**
     * Below this many responses a site's average is noise, not a rating, so we
     * report "too few responses" instead of a number that reads as a verdict.
     */
    public const MIN_RESPONSES = 5;

    /** Default lookback window for a site's score. */
    public const DEFAULT_WINDOW_DAYS = 30;

    /** @return Summary */
    public function forSite(Site $site, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = now()->subDays($windowDays);

        /** @var Collection<int, SiteSurveyResponse> $responses */
        $responses = SiteSurveyResponse::where('site_id', $site->id)
            ->where('submitted_at', '>=', $since)
            ->get(['id', 'ratings', 'submitted_at']);

        return $this->summarize($responses, $site, $since);
    }

    /**
     * Site-level summaries for many sites in one pass, keyed by site id.
     * Avoids N+1 when rendering a list.
     *
     * @param  iterable<int>  $siteIds
     * @return array<int, Summary>
     */
    public function forSites(iterable $siteIds, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $ids = collect($siteIds)->all();

        if ($ids === []) {
            return [];
        }

        $since = now()->subDays($windowDays);

        $grouped = SiteSurveyResponse::whereIn('site_id', $ids)
            ->where('submitted_at', '>=', $since)
            ->get(['id', 'site_id', 'ratings'])
            ->groupBy('site_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $this->summarize($grouped->get($id, collect()), null, $since);
        }

        return $out;
    }

    /**
     * One rollup across every response from a set of sites — the program's
     * headline score, as opposed to a per-site average of averages (which
     * would quietly over-weight quiet sites).
     *
     * @param  iterable<int>  $siteIds
     * @return Summary
     */
    public function forScope(iterable $siteIds, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $ids = collect($siteIds)->all();

        if ($ids === []) {
            return $this->emptySummary();
        }

        $since = now()->subDays($windowDays);

        /** @var Collection<int, SiteSurveyResponse> $responses */
        $responses = SiteSurveyResponse::whereIn('site_id', $ids)
            ->where('submitted_at', '>=', $since)
            ->get(['id', 'site_id', 'ratings']);

        return $this->summarize($responses, null, $since);
    }

    /**
     * Rollup by provider × transport (Plan.md S6), using the provider and
     * transport denormalized onto each response at submit time.
     *
     * Grouped by the **registry's** canonical provider, not the raw string on
     * the response: the workbook carried both "IT BUSINESS SOLUTIONS" (139
     * sites) and "IT Business Solutions" (1), so a raw grouping reported one
     * company twice — the 1-site row was invisible next to its own siblings.
     * The raw spelling stays on the response row; only the report is folded.
     * An unrecognised provider keeps its own value rather than being guessed
     * into a neighbour.
     *
     * Guarded by the same minimum-N rule: a provider with three responses must
     * not be ranked against one with three hundred.
     *
     * @param  iterable<int>|null  $siteIds  Scope to these sites; null is every site.
     * @return list<ProviderRow>
     */
    public function byProvider(int $windowDays = self::DEFAULT_WINDOW_DAYS, ?iterable $siteIds = null): array
    {
        $since = now()->subDays($windowDays);
        $scoped = $siteIds === null ? null : collect($siteIds)->all();

        return SiteSurveyResponse::when($scoped !== null && $scoped !== [], fn ($q) => $q->whereIn('site_id', $scoped))
            ->where('submitted_at', '>=', $since)
            ->get(['cms_provider', 'last_mile_tech', 'ratings'])
            ->groupBy(fn (SiteSurveyResponse $r) => $this->providerLabel($r->cms_provider).'|'.($r->last_mile_tech ?? '—'))
            ->map(function (Collection $rows, string $key) use ($since) {
                $summary = $this->summarize($rows, null, $since);
                [$provider, $transport] = explode('|', $key, 2);

                return [
                    'cms_provider' => $provider === '—' ? null : $provider,
                    'last_mile_tech' => $transport === '—' ? null : $transport,
                    'responses' => $summary['responses'],
                    'meets_minimum' => $summary['meets_minimum'],
                    'overall' => $summary['overall'],
                ];
            })
            ->sortByDesc('responses')
            ->values()
            ->all();
    }

    /** Canonical label for a recorded provider string, or the value itself. */
    private function providerLabel(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '—';
        }

        return $this->providers->label($raw) ?? $raw;
    }

    /**
     * @param  Collection<int, SiteSurveyResponse>  $responses
     * @return Summary
     */
    private function summarize(Collection $responses, ?Site $site, Carbon $since): array
    {
        $count = $responses->count();

        if ($count === 0) {
            return $this->emptySummary();
        }

        $byQuestion = [];

        foreach ($responses as $response) {
            foreach (($response->ratings ?? []) as $key => $value) {
                if (is_numeric($value)) {
                    $byQuestion[$key][] = (int) $value;
                }
            }
        }

        $averages = [];
        foreach ($byQuestion as $key => $values) {
            $averages[$key] = round(array_sum($values) / count($values), 2);
        }

        // Overall = mean of the per-question averages, so a question that was
        // answered more often does not dominate the site's headline score.
        $overall = $averages === [] ? null : round(array_sum($averages) / count($averages), 2);

        // Distribution of the first rating question (conventionally `overall`),
        // because a 3.0 from all-3s and a 3.0 from 1s and 5s are different
        // problems and the mean alone cannot tell them apart.
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $primaryKey = array_key_first($averages);
        if ($primaryKey !== null) {
            foreach ($responses as $response) {
                $value = (int) data_get($response->ratings, $primaryKey, 0);
                if ($value >= 1 && $value <= 5) {
                    $distribution[$value]++;
                }
            }
        }

        return [
            'responses' => $count,
            'meets_minimum' => $count >= self::MIN_RESPONSES,
            'overall' => $overall,
            'by_question' => $averages,
            'distribution' => $distribution,
            'response_rate' => $site === null ? null : $this->responseRate($site, $count, $since),
        ];
    }

    /**
     * The shape every consumer gets when there is nothing to say — one place,
     * so the minimum-N and distribution defaults cannot drift between callers.
     *
     * @return Summary
     */
    private function emptySummary(): array
    {
        return [
            'responses' => 0,
            'meets_minimum' => false,
            'overall' => null,
            'by_question' => [],
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'response_rate' => null,
        ];
    }

    /**
     * Responses as a share of the users the site reported. A 5.0 from two
     * responses is unreadable without knowing whether two people or two
     * thousand used the site.
     */
    private function responseRate(Site $site, int $responses, Carbon $since): ?float
    {
        $users = $site->dailyStatuses()
            ->where('date', '>=', $since->toDateString())
            ->sum('total_unique_users');

        if (! $users || $users <= 0) {
            return null;
        }

        return round(($responses / $users) * 100, 1);
    }
}
