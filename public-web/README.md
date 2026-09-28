# `public-web` — the Next.js public surface

The unauthenticated face of the DICT FreeWiFi Monitor: the per-site feedback
survey at `/s/{siteCode}`.

**This is not the ops console.** That stays Laravel + Inertia + Vue and is
untouched. See `Plan_UI.md` Part II for why the split is scoped this way.

---

## The one rule

> **Laravel decides. Next.js renders.**

Nothing that protects a respondent moved into this app. The API it reads is
`GET /api/public/surveys/{siteCode}`; the submission goes to a URL that Laravel
has already signed. This app holds no database, no session, no PII, and no
validation that matters.

## Why the browser posts to Laravel directly

The form fetches the **absolute** `submitUrl` from the browser, not through this
server. That is deliberate and it is easy to "clean up" by accident:

- Laravel derives `ip_hash` from the client IP and rate-limits per IP.
- A server-side proxy would make Laravel see **this Node server's** IP, so every
  respondent would collapse into a single duplicate and the rate limit would
  stop meaning anything.
- In production both origins are the same (nginx splits `/s/*`), so this is a
  plain same-origin POST with no CORS anywhere.

## Running it

```bash
npm install
cp .env.example .env.local     # point LARAVEL_BASE_URL at your Laravel dev server
npm run dev                    # http://127.0.0.1:3010
```

Laravel's own dev server must be on **8010** (port 8000 belongs to another of
the user's apps and answers with the wrong site rather than failing).

### Local dev and the cross-origin POST

In production the survey page and the Laravel API are one origin. In local dev
they are not (`3010` vs `8010`), so the browser's POST is cross-origin. Two
options:

- Point `LARAVEL_BASE_URL` at the public origin and test through a local proxy
  that mimics the nginx split — closest to production, and what the cut-over
  rehearsal should use.
- Or accept that the POST is cross-origin in dev only. Do **not** add a blanket
  CORS entry to the Laravel app for this; the survey POST is the one anonymous
  write in the system and its rate limit and IP hash depend on the real client
  IP arriving unmangled.

## Build and run in production

```bash
npm ci
npm run build
```

`output: 'standalone'` in `next.config.mjs` produces a self-contained server
with its own minimal `node_modules`. Copy `public-web/` plus
`public-web/.next/standalone` to the host and run the latter's `server.js`.
Supervisor unit: `deploy/public-web.conf`.

## Layout

```
app/
├── layout.tsx              Figtree, noindex, referrer:no-referrer
├── globals.css             tokens copied from Plan_UI.md Part I §1
└── s/[siteCode]/
    ├── page.tsx            server component — the whole point
    ├── SurveyForm.tsx      client, posts to the signed URL
    └── NeutralPage.tsx     unknown / not-open states
lib/
├── api.ts                  server-only fetch of the questionnaire
└── types.ts                mirrors PublicSurveyController
```

## Rules this app must keep

- **Questions are data.** Never hardcode a question key or a question count; the
  set is a seeded row and a v2 must not require a code change here. (The Inertia
  copy once said "Four quick questions" beside a form driven by the seeder.)
- **Every text question binds to the single `comments` field**, which is what
  the write path stores. Mirrors the Inertia form; two shapes would be a silent
  data split.
- **Unknown and unavailable both render 200.** A QR placard reprinted at hundreds
  of sites must never show a visitor an error page, and distinguishing the two
  would tell a stranger whether a site code exists.
- **No raw IP reaches this runtime.** It is not sent here and must not be logged
  here.
