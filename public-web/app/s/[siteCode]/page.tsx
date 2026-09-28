import type { Metadata } from 'next';

import { fetchSurvey } from '@/lib/api';
import type { SurveyOk, SurveyQuestion } from '@/lib/types';

import { SurveyForm } from './SurveyForm';
import { NeutralPage } from './NeutralPage';

export const dynamic = 'force-dynamic';

/**
 * The public survey at `/s/{siteCode}`.
 *
 * A server component: `fetchSurvey` runs here, on the server, so the site name
 * and the full question set are in the **first HTML response**. On the slow
 * connections this audience actually has, that is the entire point of the
 * split — see Plan_UI.md Part II §8.
 *
 * The three non-OK states are deliberate, and they mirror the Inertia survey
 * exactly (`resources/js/Pages/Survey/Unknown.vue`, `Unavailable.vue`):
 *
 *  - **unknown** — a stale QR or a mistyped code. Neutral page, no detail, no
 *    403. Telling a stranger whether a site code exists is itself a disclosure.
 *  - **unavailable** — no questionnaire is published. Says so plainly.
 *
 * Both render with HTTP 200. A QR placard that has been reprinted at hundreds of
 * sites should never produce an error page in a visitor's face.
 */
export default async function SurveyPage({ params }: { params: Promise<{ siteCode: string }> }) {
  const { siteCode } = await params;
  const data = await fetchSurvey(siteCode);

  if (data.status === 'unknown') {
    return <NeutralPage title="Link not recognised" body="This feedback link is not one we recognise. It may have been reprinted — please scan the code at the site again." />;
  }

  if (data.status === 'unavailable') {
    return <NeutralPage title="Feedback is not open" body="Feedback is not being collected right now. Thank you for checking." />;
  }

  return <SurveyView data={data} />;
}

export async function generateMetadata({ params }: { params: Promise<{ siteCode: string }> }): Promise<Metadata> {
  const { siteCode } = await params;
  const data = await fetchSurvey(siteCode);

  if (data.status !== 'ok') {
    return { title: 'Free WiFi Feedback' };
  }

  return { title: data.survey.title, description: `Tell us about the Free WiFi connection at ${data.site.name}.` };
}

/**
 * Counted from the question set, never hardcoded. The Inertia copy once said
 * "Four quick questions" beside a form driven by the seeded set, so a v2
 * question set would have lied to the public on its first screen. Same rule
 * here.
 */
function introCopy(questions: SurveyQuestion[]): string {
  const count = questions.length;
  return `${count} quick question${count === 1 ? '' : 's'} about the connection you are using right now. It takes under a minute.`;
}

function SurveyView({ data }: { data: SurveyOk }) {
  const { site, survey } = data;

  return (
    <div className="shell">
      <header className="identity">
        <p className="identity__title">
          <span className="identity__mark" aria-hidden="true" />
          Free WiFi Feedback
        </p>
        <p className="identity__meta">
          {site.name}
          {site.municipality || site.province ? (
            <>
              {' · '}
              {[site.municipality, site.province].filter(Boolean).join(', ')}
            </>
          ) : null}
        </p>
      </header>

      <main className="main">
        <div className="card">
          <h1 className="card__title">{survey.title}</h1>
          <p className="card__intro">{introCopy(survey.questions ?? [])}</p>

          <SurveyForm
            questions={survey.questions ?? []}
            submitUrl={data.submitUrl}
            startedAt={data.startedAt}
          />
        </div>
      </main>

      <footer className="footer">
        Your answers are anonymous. No name, email or contact details are collected.
      </footer>
    </div>
  );
}
