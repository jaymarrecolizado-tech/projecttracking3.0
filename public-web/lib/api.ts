import 'server-only';

import type { SurveyResponse } from './types';

/**
 * Server-side only fetch of the survey definition.
 *
 * This runs on the Next.js server, never in the browser. That is deliberate:
 * the browser never learns the internal Laravel base URL, and the signed submit
 * URL is minted by Laravel and handed through untouched.
 *
 * Laravel is the system of record — this function only reads (Plan_UI.md §9).
 * A Laravel outage degrades to a neutral page rather than a crash, because a
 * respondent on a captive portal must never see a stack trace.
 */
export async function fetchSurvey(siteCode: string): Promise<SurveyResponse> {
  const base = process.env.LARAVEL_BASE_URL;

  if (!base) {
    // Misconfiguration, not an outage. Fail closed and say why in the log
    // rather than sending the visitor a broken form.
    console.error('[public-web] LARAVEL_BASE_URL is not set.');
    return { status: 'unavailable' };
  }

  try {
    const res = await fetch(`${base.replace(/\/$/, '')}/api/public/surveys/${encodeURIComponent(siteCode)}`, {
      // The questionnaire is public and changes only when a new survey is
      // seeded, so a short cache absorbs a QR-code scan burst without ever
      // serving a questionnaire to the wrong site.
      next: { revalidate: 60 },
      headers: { Accept: 'application/json' },
    });

    if (res.status === 404) return { status: 'unknown' };
    if (res.status === 409) return { status: 'unavailable' };

    if (!res.ok) {
      console.error(`[public-web] survey fetch failed: ${res.status}`);
      return { status: 'unavailable' };
    }

    return (await res.json()) as SurveyResponse;
  } catch (error) {
    console.error('[public-web] survey fetch threw:', error);
    return { status: 'unavailable' };
  }
}
