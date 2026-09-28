/**
 * The contract between this app and the Laravel API at
 * `GET /api/public/surveys/{siteCode}` (Plan_UI.md Part II §9).
 *
 * These mirror `App\Http\Controllers\Api\PublicSurveyController` exactly. The
 * question shape is the one documented on `App\Models\SiteSurvey`: questions are
 * data, so a seeded v2 question set must not require a code change here — which
 * is why `questions` is typed as a union and driven off `type`, not off
 * hardcoded keys.
 */

export type QuestionType = 'rating' | 'choice' | 'text';

export interface SurveyQuestion {
  key: string;
  label: string;
  type: QuestionType;
  required: boolean;
}

export interface SurveySite {
  name: string;
  barangay: string | null;
  municipality: string | null;
  province: string | null;
}

export interface SurveyDefinition {
  title: string;
  questions: SurveyQuestion[];
}

/** 200 from the API. */
export interface SurveyOk {
  status: 'ok';
  site: SurveySite;
  survey: SurveyDefinition;
  /** Absolute, signed, already-minted by Laravel. Post to it verbatim. */
  submitUrl: string;
  startedAt: number;
}

/** 404 from the API — a stale QR or a typo. Deliberately neutral. */
export interface SurveyUnknown {
  status: 'unknown';
}

/** 409 from the API — no questionnaire is published right now. */
export interface SurveyUnavailable {
  status: 'unavailable';
}

export type SurveyResponse = SurveyOk | SurveyUnknown | SurveyUnavailable;

/** Answers keyed by question key. Text is NOT included — see `postSurvey`. */
export type Ratings = Record<string, number>;
