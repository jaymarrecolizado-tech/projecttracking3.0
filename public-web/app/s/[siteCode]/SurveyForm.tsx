'use client';

import { useMemo, useRef, useState } from 'react';

import type { Ratings, SurveyQuestion } from '@/lib/types';

const RATING_WORDS: Record<number, string> = {
  1: 'Very poor',
  2: 'Poor',
  3: 'Okay',
  4: 'Good',
  5: 'Very good',
};

/**
 * The survey form.
 *
 * Two things here are load-bearing and easy to break:
 *
 * 1. **The browser posts to `submitUrl` directly, not through this server.**
 *    `submitUrl` is an absolute, signed URL on the Laravel origin. Posting from
 *    the browser is what preserves the respondent's real client IP in Laravel's
 *    rate limiter and `ip_hash` — a server-side proxy would hash *this Node
 *    server's* IP and collapse every respondent into one duplicate. In
 *    production the two origins are the same (nginx splits `/s/*`), so this is a
 *    same-origin POST with no CORS involved.
 *
 * 2. **Every text question binds to the single `comments` field**, which is what
 *    the write path stores. This mirrors the Inertia form deliberately: two
 *    surfaces posting different shapes would be a silent data split.
 */
export function SurveyForm({
  questions,
  submitUrl,
  startedAt,
}: {
  questions: SurveyQuestion[];
  submitUrl: string;
  startedAt: number;
}) {
  const ratingQuestions = useMemo(() => questions.filter((q) => q.type === 'rating'), [questions]);
  const textQuestions = useMemo(() => questions.filter((q) => q.type === 'text'), [questions]);

  const [ratings, setRatings] = useState<Ratings>(() =>
    Object.fromEntries(ratingQuestions.map((q) => [q.key, 0])),
  );
  const [comments, setComments] = useState('');
  const [website, setWebsite] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);

  // Server-rendered timestamp, so the elapsed measure starts when the page was
  // painted rather than when React hydrated.
  const shownAt = useRef<number | null>(null);
  if (shownAt.current === null) shownAt.current = Date.now();

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (sending) return;

    setSending(true);
    setErrors({});

    const payload = {
      ratings: Object.fromEntries(Object.entries(ratings).filter(([, v]) => v > 0)),
      comments: comments || null,
      website,
      elapsed_ms: Date.now() - (shownAt.current ?? Date.now()),
    };

    try {
      const res = await fetch(submitUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        // Same-origin in production; the absolute URL is used so the signature
        // matches the exact URL Laravel signed.
        credentials: 'omit',
        body: JSON.stringify(payload),
      });

      if (res.ok) {
        setDone(true);
        return;
      }

      if (res.status === 422) {
        const body = (await res.json().catch(() => ({}))) as {
          errors?: Record<string, string[]>;
          message?: string;
        };
        const flat: Record<string, string> = {};
        for (const [key, messages] of Object.entries(body.errors ?? {})) {
          if (messages?.[0]) flat[key] = messages[0];
        }
        setErrors(flat);
        setSending(false);
        return;
      }

      // 403 (bad signature), 409 (unavailable) or anything else: say only that
      // it did not work. Never surface a status a prober could learn from.
      setErrors({ __form: 'We could not send that. Please try again.' });
      setSending(false);
    } catch {
      setErrors({ __form: 'We could not send that. Please try again.' });
      setSending(false);
    }
  }

  if (done) {
    return (
      <div className="card__intro" style={{ marginTop: '1.75rem' }} role="status">
        <p style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--ink)' }}>Thank you.</p>
        <p style={{ marginTop: '0.5rem' }}>
          Your feedback has been recorded. It is reviewed alongside the site&apos;s uptime data.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate>
      {/* Honeypot — a real respondent never sees this. */}
      <div className="honeypot" aria-hidden="true">
        <label htmlFor="website">Website</label>
        <input
          id="website"
          name="website"
          type="text"
          tabIndex={-1}
          autoComplete="off"
          value={website}
          onChange={(e) => setWebsite(e.target.value)}
        />
      </div>

      {ratingQuestions.map((q) => (
        <fieldset className="field" key={q.key}>
          <legend className="field__legend">{q.label}</legend>
          <div className="ratings">
            {[1, 2, 3, 4, 5].map((n) => (
              <button
                key={n}
                type="button"
                className="rating"
                aria-pressed={ratings[q.key] === n}
                aria-label={`${n} of 5 — ${RATING_WORDS[n]}`}
                onClick={() => setRatings((prev) => ({ ...prev, [q.key]: n }))}
              >
                <span className="rating__value tabular">{n}</span>
                <span className="rating__word">{RATING_WORDS[n]}</span>
              </button>
            ))}
          </div>
          {errors[`ratings.${q.key}`] ? (
            <p className="error" role="alert">
              Please choose a rating.
            </p>
          ) : null}
        </fieldset>
      ))}

      {textQuestions.map((q) => (
        <div className="field" key={q.key}>
          <label className="field__label" htmlFor={`q-${q.key}`}>
            {q.label}
          </label>
          <textarea
            id={`q-${q.key}`}
            className="textarea"
            rows={3}
            maxLength={2000}
            placeholder="Optional"
            value={comments}
            onChange={(e) => setComments(e.target.value)}
          />
        </div>
      ))}

      {errors.__form ? (
        <p className="error" role="alert">
          {errors.__form}
        </p>
      ) : null}
      {errors.ratings ? (
        <p className="error" role="alert">
          {errors.ratings}
        </p>
      ) : null}
      {errors.website ? (
        <p className="error" role="alert">
          Submission was rejected. Please try again.
        </p>
      ) : null}

      <button type="submit" className="submit" disabled={sending}>
        {sending ? 'Sending…' : 'Submit feedback'}
      </button>
    </form>
  );
}
