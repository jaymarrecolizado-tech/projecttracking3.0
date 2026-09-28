# Plan — DICT FreeWiFi Monitor

Living roadmap. Each workstream is a numbered **Plan#N** below. **Done** = in the local repo. **Open** = not built, or built locally but not on production.

Companion docs: `Plan_revision.md` (2026-09-08 hardening log; §3b/§3c are the 2026-09-14 verification) · `Plan_ui.md` (visual roadmap).

---

## Plan index

| Plan | Workstream | Status | Blocked on |
|---|---|---|---|
| **Plan#1** | [Fortify before deploy](#plan1--fortify-before-deploy) | 🟡 **In progress** (F1–F2, F5, F6 done; F3/F4/F7 open) | owner (Sentry DSN, the monitor itself, restore drill, retention call) |
| **Plan#2** | [Ship to production (cutover)](#plan2--ship-to-production-cutover) | ⏸️ **ON HOLD** — do not start | Plan#1 completion |
| **Plan#3** | [Per-site user survey & feedback](#plan3--per-site-user-survey--feedback) | 🟢 **S1–S8 implemented** (rollout open) | owner (entry point, question set, retention) |
| **Plan#4** | [Multi-provider / multi-NMS](#plan4--multi-provider--multi-nms) | 🟡 **Registry + normalize built**; NMS binding still blocked | owner (which providers are in polling scope) |
| **Plan#5** | [Analytics PDF reports](#plan5--analytics-pdf-reports) | 🟡 Phases 1–5 **code-complete**, SLA target unset | DICT (SLA target, solar data) |
| **Plan#6** | [Operational readiness gaps](#plan6--operational-readiness-gaps) | 🟡 8 of 11 closed | owner (Sentry DSN, external monitor) |
| **Plan#7** | [Owner-input backlog](#plan7--owner-input-backlog-not-scheduled) | ⚪ **Not scheduled** — no spec exists | owner |
| — | [Completed (archive)](#completed-archive) | ✅ | — |

**Gate state (re-run 2026-09-27, after the satisfaction-report + QR + incident-runbook + provider-registry slice):** **297 tests / 1,337 assertions** · PHPStan **level 6, 0 errors** · Pint PASS · ESLint 0/0 · Vitest 11 (3 files) · `npm run build` clean · `config:cache`/`event:cache`/`route:cache`/`view:cache` all clean. Previous snapshots: 277 / 1,269 (earlier the same day) → 255 / 1,195 (2026-09-22) → 238 / 1,121 (2026-09-14). One test skips on Windows only (a POSIX mode bit cannot make a directory unwritable there; the check it guards runs on the Linux host). The suite is green on **both** the SQLite and `pdo_mysql` driver paths, and CI runs the latter on `mysql:8.0` with `sql_mode` pinned (see Plan#1 → F1). **Not re-run this pass:** the `mysql:8.0` job (needs a MySQL server) — CI will confirm on push.

**Production (`fpiapr2.dictr2.cloud`):** still an older tree. GitHub `main` is now at `57d20bc` (Laravel 12.69.2). `route:cache` is safe — verified: exactly one route named `dashboard`, and `/dashboard` is a 301 redirect to `/`.

---

## Plan#1 — Fortify before deploy

**Status: 🟡 in progress — current focus.** Deployment is **on hold by owner decision** (2026-09-14) — harden first. The feature slice is complete and every gate is green, so the remaining risk is not "unfinished features"; it is things that only misbehave under production conditions and that the current tests structurally cannot catch. Ordered by "what breaks in production that nothing here can see yet".

> **Re-verified 2026-09-14 against HEAD `57d20bc`.** Every `[x]`/✅ in this section was re-checked against the tree this pass (file, route or test named), not taken from the marker — the markers have gone stale repeatedly because parallel sessions edit this file. F1 and all three F2 items have since **shipped**; one new **CSP regression** was found and reproduced. Everything left as `[ ]` was confirmed genuinely still open.
>
> **Updated 2026-09-27:** F5 (PHPStan L6) and F6 (incident runbook) are closed, F3's log-rotation half is decided and implemented, and two of F4's three drills are now automated checks. The three that remain all need a person with server access or an owner decision, and each says so where it sits.

**Summary:** F1, F2, F5 and F6 complete. Still open: **F3** (Sentry DSN, `/up` monitor), **F4** (restore drill + the supervisor restart that only a real server can prove), **F7** (audit-retention decision).

### F1 — Prove it on MySQL *(✅ complete 2026-09-14)*

**Done:** `.github/workflows/ci.yml` now has a second job, `php-mysql`, running `image: mysql:8.0` with `pdo_mysql` and `MYSQL_DATABASE: pred_tracking_mysql_test`, executing `php vendor/bin/phpunit -c phpunit.mysql.xml`. `phpunit.mysql.xml` is **committed** (not the untracked scratch harness this section previously described). MySQL 8.0 was chosen over MariaDB deliberately — the JSON-type difference below is the reason.

The measurement that motivated it, kept for the record — the suite was run against a MySQL-protocol server (`pdo_mysql`) with MySQL 8's strict modes forced on:

| Run | Engine / `sql_mode` | Result |
|---|---|---|
| Baseline | SQLite `:memory:` | 231 passed / 1,085 assertions |
| MySQL driver | MariaDB 10.4.32, lax | **231 passed / 1,085 assertions** |
| MySQL driver + MySQL 8 strictness | MariaDB 10.4.32, `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` | **231 passed / 1,085 assertions** |

`migrate:fresh` also runs clean on the MySQL grammar. **The predicted fallout did not materialise** — no `ONLY_FULL_GROUP_BY` failures on grouped `selectRaw`, no DECIMAL-as-string or collation failures. Both driver-specific branches **did execute** under `pdo_mysql` and passed:

- `ImportService::nextAssetTag()` — `Phase5FeaturesTest:167` invokes it directly (tags `FW-0001/0007/0002` → expects `FW-0008`), so `MAX(CAST(SUBSTRING(asset_tag, 4) AS UNSIGNED))` genuinely ran. The earlier claim that "the MySQL expression has never executed in any test" is **false as of this measurement**.
- `BackfillSiteDistricts` — `LegislativeDistrictBackfillTest` drives the command, taking the `DB::getDriverName()` MySQL `JOIN … UPDATE` branch.

The divergence that made MySQL 8 (not MariaDB) the required service: all **11** `$table->json()` columns are stored as **`longtext`** on MariaDB but become the **native `json` type** on MySQL 8, which *normalizes* documents on write (keys reordered, whitespace stripped, duplicate keys dropped). The risk is contained — every JSON column is cast `'array'` in its model and tests assert on decoded arrays (`AuditLogTest:49`, `BarangayCoverageTest:282`, `SiteDedupeTest:87`), so key reordering is invisible. Still unexercised locally: MySQL 8's optimizer/query plans, native-JSON indexability (needs generated columns), and collation defaults.

Residuals:

- [x] **`sql_mode` pinned in CI** (done 2026-09-14) — the `php-mysql` job now has a `Pin MySQL 8 strict modes` step running `SET GLOBAL sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,…'` before the tests, so strictness is asserted rather than inherited from the image tag. Verified the statement works and that a **new connection** inherits it (`@@SESSION.sql_mode` matches `@@GLOBAL.sql_mode`) — which is what matters, since each test opens a fresh connection. CI YAML re-parsed as valid.
- [x] **JSON round-trip test** (done 2026-09-14) — `JsonColumnRoundTripTest`, 3 tests / 19 assertions, green on **both** driver paths. It pins the only contract the app may rely on: **values** survive the `array` cast (nested objects, lists, unicode `Peñablanca — naïve café`, empty string, `0`, `true`/`false`, `null`, float, empty list), while **key order is explicitly not part of it** — keys are compared with `assertEqualsCanonicalizing`, because MySQL 8 reorders object keys and SQLite does not, so an order-sensitive assertion would pass locally and fail in production. A third case writes raw JSON past the cast (`{ "padded" : 1 , "dup" : 2 , "dup" : 3 }`) and asserts it still decodes, with duplicate keys resolving to the **last** value — true on MySQL 8 (collapsed on write) and on SQLite/MariaDB (collapsed by `json_decode` on read), so the outcome is engine-independent.
  *Caveat worth keeping:* MariaDB stores these as `longtext`, so this test **cannot** exercise MySQL 8's native JSON type locally — it pins the portable contract. The CI `mysql:8.0` job is what actually runs it against the real type.
- [ ] Confirm the production MySQL version and its `sql_mode`; if it is MySQL 8, the third row above is representative.

### F2 — Close the exploitable gaps *(✅ complete 2026-09-14)*

✅ **Closed 2026-09-13 (verified in tree — do not redo):**

- Heartbeat race — `lockForUpdate()` inside the existing transaction in `Api/HeartbeatController`; double-post keeps one row (`RequestHardeningTest`).
- `Api/SiteApiController` — `per_page` validated and capped at 200; `show()` history capped at latest 90. The identical hole in `Api/DailyStatusApiController` was capped too (`RequestHardeningTest`, `ApiCoverageTest`).
- `UserController` — `$request->boolean('status')`, so `?status=false` is no longer truthy (`RequestHardeningTest`).
- `.env.production.example` — `SESSION_DRIVER=file`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=file`, `CACHE_PREFIX=freewifi_monitor`.

✅ **Closed 2026-09-14 (all three re-verified in tree — do not redo):**

- **CSV formula injection.** `exportCsv()` neutralises cells at the single choke point covering all eight packs; `csvSafe()` returns non-strings and empty strings untouched, else prefixes `'` when the first char is `= + - @` `\t` `\r`. `test_csv_neutralizes_formula_injection` asserts `'=HYPERLINK` is present and `,=HYPERLINK` is not.
- **The map no longer touches a public CDN.** `leaflet@1.9.4` + `leaflet.markercluster@1.5.3` are npm dependencies bundled by Vite — confirmed inside the built map chunk (`markerClusterGroup` + Leaflet CSS classes present) — and **zero `unpkg` references remain** in `resources/`, `app/`, `config/` or `manifest.json`. Leaflet is now lockfile-pinned and covered by `npm audit`. Pure helpers were split into `Map/mapHelpers.js` because Leaflet touches `window` at import and otherwise broke Vitest.
- **CSP + HSTS.** `SecurityHeaders` emits a nonce-based CSP (`script-src 'self'` + per-request nonce; Ziggy `@routes` bypassed for a direct `generate(nonce:)` since the directive takes no nonce arg) plus HSTS, **production-only** (Vite HMR needs inline/ws; HSTS would poison local http). `SecurityHeadersTest` asserts the served nonce matches the policy, and that non-production serves neither header.

🔴 **A regression the 2026-09-14 CSP pass shipped — found, fixed and guarded the same day. Nothing in F2 is left open.** Found by rendering `/` under `APP_ENV=production` and enumerating every `<script>` tag:

  | # | Tag | Nonce (before fix) | Verdict |
  |---|---|---|---|
  | 0 | Ziggy inline (`type="text/javascript"`) | present, matches header | ✅ |
  | 1–2 | `<script type="module" src=…>` | n/a (external) | ✅ allowed by `'self'` |
  | 3 | **inline `<script>` (Vite prefetch)** | **absent** | ❌ **blocked** |

  Cause: `SecurityHeaders` shared the nonce with **Blade views only** (`view()->share('cspNonce', …)`), but Laravel's `Vite` class keeps its **own** `$nonce`, and `Vite::useCspNonce()` was never called — so `nonceAttribute()` returned `''` for the inline prefetch bootstrap (`Foundation/Vite.php:512`, emitted because `AppServiceProvider:36` calls `Vite::prefetch(concurrency: 3)` → `waterfall` → not hot → block runs).
  **Fix applied:** `Vite::useCspNonce($nonce);` beside the `view()->share(...)` in `SecurityHeaders::handle()` — `Vite` is a container singleton (`FoundationServiceProvider.php:58`) and `compileVite` resolves it, so the same nonce now lands on Laravel's tags.
  **Test gap closed:** `SecurityHeadersTest` previously regex-matched only the *Ziggy* script, which is why this shipped. New `test_every_inline_script_carries_the_header_nonce` collects **every** `<script>` with no `src` and asserts each carries the header nonce. Confirmed it genuinely catches the bug — reverted the fix and it fails with `every inline <script> must carry the CSP nonce, got: <script>`.
  *Lesson worth keeping:* a nonce-based CSP must assert **every** inline script, not the one you remembered — and dev never serves the policy, so local browsing cannot see this class of break at all.

### F3 — Make failures visible before users report them

✅ **Closed 2026-09-13:** `deploy/fpiap-worker.conf` (Supervisor unit) committed, and `queue:check` reports a stuck-PENDING backlog (exit 1, for cron/monitors) — covered by `QueueCheckTest` and wired into `docs/DEPLOY.md` §3.

Still open:

- [ ] **Sentry.** Installed and wired (`sentry/sentry-laravel ^4.27`, `Sentry\Laravel\Integration` in `bootstrap/app.php`, providers auto-discovered) but `.env.production.example:70–71` still has `SENTRY_ENABLED=false` and an empty DSN — production errors currently go nowhere. Set the DSN (**config only, no code change**) and **verify one real event lands**.
- [ ] **External monitor on `/up` — the endpoint is now worth monitoring; the monitor itself is the owner's.** `App\Listeners\CheckSystemHealth` subscribes to `DiagnosingHealth` and fails the endpoint when the database is unreachable or `storage/` is unwritable, so `/up` answers 200 only when the app could actually serve a page. Before this, a monitor pointed at `/up` would have been told everything was fine while every page 500'd on a dead MySQL — the failure mode the runbook's §4 lists. `HealthCheckTest` covers 200-when-healthy, both failure paths, and that the endpoint stays unauthenticated. **What is left is creating the monitor** (any hosted uptime service, pointed at `/up`, 1-minute interval) — that needs an account and a decision about who gets paged, so it stays open.
- [x] **Log rotation and retention — decided 2026-09-27: Laravel's daily channel owns rotation, `logs:prune` owns expiry.** A logrotate rule was written and then deleted: the daily channel already rotates by *name* (`laravel-2026-09-26.log`), so logrotate could only ever rename each day's file once and would never expire it — two rotators on one file is worse than one. `App\Console\Commands\PruneLogs` (`logs:prune --days=30`, scheduled 03:20) deletes dated files past the window and deliberately never touches `laravel.log` (the file being written) or the supervisor stdout log. Answer to "how far back do the logs go" is now **30 days**, versioned in the repo and covered by `OperationalDrillTest::test_log_prune_keeps_the_window_and_deletes_the_rest`. The window must still be reconciled with the 90-day audit trail (F7).

### F4 — Prove recovery, not just backup

- [ ] **Restore drill.** `docs/DEPLOY.md` §5.6 already documents the quarterly steps — but no restore has ever actually been run. Execute it once: restore last night's dump into a scratch database, boot the app against it, time it, and record the result (and any step that turned out to be wrong) back into §5.6. A backup that has never been restored is a hypothesis. **This is the one drill that genuinely needs the server** — it restores a dump.
- [x] **Queue failure drill — automated 2026-09-27.** The *code path* half of this is now a runnable check rather than prose: `OperationalDrillTest` drives `ProcessExcelImport::failed()` and asserts the batch is marked `FAILED`, keeps the exception text, writes an `audit_logs` row, and can be re-queued cleanly; a second test drives the stuck-PENDING signal that `queue:check` exposes. What is still **not** proven, and cannot be from a laptop, is that supervisor actually restarts a killed worker — that is the remaining owner-side half.
- [x] **Degradation check — automated 2026-09-27.** `OperationalDrillTest` fakes an unreachable Telegram (a `ConnectionException`, i.e. a hang that times out rather than a 500) and asserts: the client returns `false` instead of throwing, `/alerts` and `/sites` still serve 200, the public survey still records a response, and `alerts:evaluate` still completes with every delivery channel dead. The alert is still *recorded* — the loss is the notification, which is exactly the trade the runbook describes.

### F5 — Test depth

✅ **Closed 2026-09-13 (verified in tree — do not redo):**

- `PageSmokeTest` — HTTP render smoke over `/`, `/daily-ops`, `/map`, `/reports`, `/wallboard` (asserts 200 + component). This is the layer that would have caught both shipped bugs (blank Projects page, Sites 500).
- `ReportContentTest` — renders the incidents/progress/fleet Blades with seeded data and asserts key figures **and** narrative bullets land on the page, so a template producing wrong numbers now fails.
- `ApiCoverageTest` — `probe-tokens.destroy` (own revoke works, another user's id → 404) and `/api/daily-statuses` + `/api/daily-statuses/site/{site}` (filters, scoping, per_page cap).

Still open:

- [ ] **Playwright smoke** — optional, and deliberately not taken. The HTTP layer is already covered by `PageSmokeTest`; Playwright would add client-side console-error and hydration coverage, at the cost of browser binaries + a JS-capable CI runner. Nothing in this pass suggests a client-side defect that a static review missed.
- [x] **PHPStan level 6 — closed 2026-09-27: 0 errors, and the gate moved to L6.** 202 errors → 0 (the plan's earlier count was 199; two more had been introduced). The work was one shaped-data pass, not a suppression pass — no baseline, no `@phpstan-ignore`, no type widening to make errors go away:
  - **Shared shapes are declared once, next to the service that builds them**, and imported by the readers: `ReportAnalytics` owns `ScopeParams` / `Bundle` / `TrendDay` / `DownEpisode`; `SiteCoverageService` owns `Filters` / `Row` / `SiteTotals` / `SiteCoverage`; `BarangayCoverageService` the same for barangays; `SiteSurveyAnalytics` owns `Summary` / `ProviderRow`; `ReportingService` owns `Comparison` / `Inventory` / `Incidents` / `Progress` / `Satisfaction` / `Csv`. `ReportNarrative` imports them, so a narrative cannot read a key the data no longer produces.
  - **It found real defects, not just noise.** Two of them are the reason this was worth doing:
    | # | Defect | Fix |
    |---|---|---|
    | 1 | `project-summary.blade.php` read `site_coverage.covered/total` and `barangay_coverage.total` — **keys no service produces** — so both coverage cells in the *executive* PDF silently rendered `0 / 0` through a `data_get(..., 0)` default | read the real keys (`actual/registered`, `covered/barangays`); `ReportContentTest` now pins both cells with a non-zero denominator so the assertion can actually bite |
    | 2 | `SiteSurveyAnalytics::summarize()` hardcoded the 30-day window when computing `response_rate`, so any caller asking for a 7-day window got a 7-day numerator over a 30-day denominator | `$since` is now a parameter of `summarize()`; `test_response_rate_uses_the_window_it_was_asked_for` pins 0.2% vs 0.1% |
  - `ReportAnalytics::downEpisodes()` now returns a `list` rather than a `Collection`. Laravel's `Eloquent\Collection::map()` is declared `@return Support\Collection<TKey, TMapValue>|static<TKey, TMapValue>` — a union PHPStan cannot match against a declared shape, and its `TModel` template cannot hold an array. Returning the list (as `alerts.latest` and `tickets.latest` already did) keeps the whole bundle one shape; callers are `!== []` / `[0]` and `count()` still work.
  - `phpstan.neon` is now `level: 6`, `composer analyse` and the CI step name were updated, and `README.md` no longer claims L5. **The gate is real, not a note in a document.**

### F6 — Before handover

- [x] **Operator manual** — done 2026-09-13: `docs/USER_GUIDE.md` (69 lines) covers the Daily Ops lifecycle (`DRAFT → SUBMITTED → APPROVED`, locked rows final, 07:00 reminder / 23:00 snapshot), the map filters and legend buckets, and both report paths. Ops-side stays in `docs/DEPLOY.md`.
- [x] **Incident runbook** — done 2026-09-27: `docs/INCIDENT_RUNBOOK.md`. Written against the code, not from memory: the two alert paths are separated (`alerts:down` watches the *board-entered* daily status, `alerts:evaluate` watches *probe telemetry* — confusing them sends the reader to the wrong place), each of the five seeded rules has its first diagnostic, `duration_minutes` is explained as "held continuously" vs "latest sample", the recipient list is the real resolution chain (`daily.approve` on the owning project + `WATCHDOG_EMAIL` + Telegram, skipping `info`), and there are runbooks for a stuck queue, a dead console, log retention and notifier degradation.
  - ⚠️ **One deliberate gap, flagged not hidden:** the "who to call" table is a *role* summary plus an empty escalation table. A role is not a phone number, and inventing one would be worse than leaving it blank — the owner fills that table in.

### F7 — Data lifecycle

- [x] **417 soft-deleted duplicate sites — decided: retain** (2026-09-13). `HeartbeatController` resolves stale AP codes through the trashed rows' `metadata.merged_into`, so deleting them would turn old AP codes into 404s. Storage is negligible; revisit at 10× growth.
- [ ] **Confirm the `audit:prune` 90-day retention satisfies the audit requirement.** The prune exists and is scheduled monthly; what is missing is an owner statement that 90 days *is* the required window — otherwise the trail may be deleted before an audit needs it.

---

## Plan#2 — Ship to production (cutover)

**Status: ⏸️ ON HOLD — do not start.** Owner decision (2026-09-14): fortify the app first — see Plan#1. Kept here so the runbook survives.

Target: `fpiapr2.dictr2.cloud`.

- When it resumes: migrate (`2026_09_08_*`), `deploy.sh` runs `sites:backfill-regions` itself, Vite to **both** web root `build/` and `fpiap-app/public/build` (`public/build` is gitignored). Preserve `.env`. `route:cache` is OK. Split CloudPanel layout: do not run `deploy.sh` as-is without copying `public/build` to the domain folder.
- ⚠️ Do **not** rely on the migration to fill `sites.region`: it runs before the workbook import, so it matches zero rows (measured locally — coverage stayed at 14.3%). The `sites:backfill-regions` step in `deploy.sh` is what does the work. If you deploy by hand, run it yourself and confirm it reports 100%.
- ⚠️ Prerequisite before cut-over: ~~F1 (MySQL CI)~~ — **done 2026-09-14.** CI runs the suite on `mysql:8.0` with `sql_mode` pinned, plus a JSON round-trip test (see Plan#1 → F1), so the tree is no longer shipping untested against the production engine. No F1 blocker remains.
- ✅ The CSP regression (F2) that would have fired on every production page load is **fixed and now covered by a test** — no longer a cut-over blocker.
- ⚠️ **New since the survey slice:** run `php artisan db:seed --class=SiteSurveySeeder` on cut-over (idempotent) — see Plan#3.

---

## Plan#3 — Per-site user survey & feedback

**Status: 🟢 S1–S8 implemented 2026-09-27.** Rollout items remain.

**Goal.** Give the people actually connected at a Free WiFi spot a short way to rate the experience, and make every response **provably attributable to the site they were on** — so per-site satisfaction becomes ground truth that sits *beside* the uptime numbers, not a self-reported guess.

**Implementation approach (decided 2026-09-22): Option 3 first, Option 2 reuse.** The backend is delivery-agnostic — a signed per-site URL (`GET|POST /s/{siteCode}`) that works equally from a printed QR code *or* an AP-vendor guest portal's redirect URL. Option 3 (QR/splash link) requires no network access and ships now; Option 2 (TP-Link Omada / Ruijie / UniFi guest portals pointing their custom redirect at the same URL) multiplies the response rate site-by-site with zero backend change. Option 1 (DNS/DHCP intercept via Squid/CoovaChilli) was ruled out: it needs a router under our control at all 1,132 sites, which the mixed-provider fleet does not have.

**Why it belongs here.** The app could say a site was `UP`; it could not say a site was *usable*. This is also the only feature that puts the app in front of an **unauthenticated public user** — it is the app's first and only public route, so it was built under the same "prove it before expose it" lens as Plan#1.

**Explicitly out of scope for v1:** no login for respondents, no PII, no public-facing dashboards, no SMS delivery of the survey link, no free-text moderation queue (keyword filter only).

### What shipped

| Piece | Where |
|---|---|
| Signed site-code resolution, shared with heartbeat | `app/Services/SiteCodeResolver.php` — **the drift risk S2 flagged is closed**: `HeartbeatController` and the survey now call one resolver, so a `sites:dedupe` merge cannot silently lose surveys |
| Tables | `2026_09_22_000001_create_site_survey_tables` — `site_surveys` (questions as JSON data) + `site_survey_responses` (no PII, `ip_hash` = sha256(ip+APP_KEY), provider denormalized at submit time) |
| Models / factory / seeder | `SiteSurvey`, `SiteSurveyResponse`, `SiteSurveyFactory`, `SiteSurveyResponseFactory`, `SiteSurveySeeder` (v1 = 4 questions, idempotent `updateOrCreate` on `code`) |
| Public route (first in the app) | `routes/web.php` — `survey.show` / `survey.store` (signed + `throttle:survey`) / `survey.thanks`, **outside** the `auth` group |
| Rate limiter | `AppServiceProvider` — `survey`: 5/min **and** 30/day per IP (the API limiter is 120/min, far too loose for the only anonymous write) |
| Controller / request / service | `SiteSurveyController`, `StoreSiteSurveyResponseRequest` (closed rating bag — unknown keys rejected), `SiteSurveyService` (soft duplicate suppression, provider capture) |
| Aggregation | `app/Services/SiteSurveyAnalytics.php` — per-site + bulk `forSites()` (no N+1) + program-wide `forScope()` + `byProvider()` (provider × transport, optionally scoped to a set of sites), **`MIN_RESPONSES = 5` guard**, response rate from `total_unique_users` |
| Public UI | `PublicSurveyLayout.vue` (**not** `GuestLayout` — that shell says "Authorized DICT personnel only" and must never face the public), `Survey/Form.vue`, `Thanks.vue`, `Unknown.vue`, `Unavailable.vue` |
| Admin surface | CSAT panel on Site Show (with the site's survey link for field printing); a **User rating column on Sites index** (the satisfaction payload was previously fetched and then never rendered) |
| Field print | `GET /sites/survey-qr` → `resources/views/sites/survey-qr.blade.php` — one printable placard per site (name, address, AP code, QR), scoped by project/province/district/municipality, linked from the Sites filter bar and the Reports packs card |
| Reports | `satisfaction` report type — `ReportingService::satisfactionData()` + `sections/satisfaction.blade.php` + `ReportNarrative::forSatisfaction()` + `site-satisfaction.csv`; also selectable in the report builder |
| URL source of truth | `SiteSurvey::urlFor(?string $siteCode)` — one method for the printed placard, the Site Show link and the portal redirect URL, so they cannot disagree |
| Tests | `SiteSurveyTest` (24 tests) + a public-CSP test in `SecurityHeadersTest` |

**Anti-abuse, as built (S5):** honeypot (`website` → `prohibited`), minimum time-on-form (3 s), soft duplicate suppression (a returning respondent *replaces* their answers rather than inflating the count), signed POST (unsigned → 403), bounded ratings, closed question keys, IP **never stored** — only its hash.

**The honesty note stands (S5):** this cannot distinguish a connected user from someone standing nearby on the same AP, and a determined respondent can still submit once per day. That is the deliberate trade for not collecting identifying data, and it is recorded here so no one later reads a satisfaction score as more rigorous than it is. The report pack repeats the limit on its own face: it states the minimum-N it applies and prints "too few" instead of a number.

### S8 — reporting and printing (2026-09-27)

**Satisfaction in the packs.** `satisfaction` is a full member of the report family, not a bolt-on: single-pack PDF, a section inside the combined pack, a CSV companion, and a checkbox in the builder. Two deliberate choices:

- The headline figure is the mean over **every rating answer in the window**, not the mean of per-site means — averaging averages quietly over-weights the quiet sites, which are the ones a manager least wants to flattered. `forScope()` exists for exactly this and `test_scope_rollup_averages_every_answer_not_the_site_averages` pins 4.6 against a 5.0 that mean-of-means would have printed.
- `byProvider()` takes an optional site list, so a province report cannot quote national provider numbers. Pinned by `test_provider_rollup_can_be_scoped_to_the_sites_in_a_report`.
- The pack lists only *rated* sites (≥5 responses) and prints the count of below-minimum ones separately, then the provider rollup and the recent free-text remarks — the remarks are the actionable part of a bad score, escaped by Blade and formula-guarded on CSV export.

**QR placards for the field.** `chillerlan/php-qrcode` was *already* a dependency (device labels, 2FA), so this added no package. Two decisions worth recording:

- A plain Blade page, not Inertia and not a queued DomPDF PDF — same reasoning as `devices.label`: a wall placard has to print crisp, and 1,132 inline SVGs would have to be rasterised to survive DomPDF. It prints from the browser, per municipality, which is how a field run actually happens.
- Capped at 200 placards per sheet, and the page **states the truncation** ("Showing 200 of 201 sites") — the `siteTypeAppendix` 200-row cap is on record as having silently dropped rows, so the sheet refuses to repeat that. Drop the cap if a province-wide run is ever needed.
- The public URL is now **unsigned**. `survey.show` carries no `signed` middleware (a mistyped link must fail to a neutral page, not a 403), so the old signature was decorative: it lengthened every printed QR and was computed against whatever host rendered the page. The half that needs signing — the POST — is still signed, minted per render.

### Gates (2026-09-27, all green)
**277 tests / 1,269 assertions** (was 255/1,195) · PHPStan **level 6, 0 errors** · Pint PASS · ESLint 0/0 · Vitest 11 (3 files) · `vite build` clean.

**Live-verified 2026-09-22** (real session, curl): `GET /s/{code}` → 200 unauthenticated with the real site name rendered; signed POST → 302 to thanks, row count incremented; stored row carried ratings + comment + **provider/transport captured from the site** + a 64-char `ip_hash` and no raw address. Two bugs found and fixed during that check: admin `is_active` was NULL (login impossible) and `APP_URL` pointed at a dead port (all generated links wrong).

**The 2026-09-27 additions are covered by tests, not a live session** (no server reachable from here): the QR sheet (placard per site, base64 SVG `src`, filter scoping, auth, truncation notice), the satisfaction report (queued → `DONE` → real `%PDF` bytes → CSV content), and the satisfaction Blade (figures land, below-minimum sites do **not** appear, a submitted `<script>` remark is escaped). Worth one manual print on a real browser before the field run.

### Still open / needs owner input

- [ ] **Deploy the question set** — `SiteSurveySeeder` is wired into `DatabaseSeeder`; production needs `db:seed --class=SiteSurveySeeder` (idempotent) on cut-over.
- [x] **QR generator for the field** — done 2026-09-27 (`GET /sites/survey-qr`, see S8 above). Remaining: print one and confirm it scans on a phone before the field run.
- [ ] **Option 2 (AP-vendor portal) rollout** — needs the owner to confirm which portals exist at the sites; the redirect URL is already stable (`SiteSurvey::urlFor()`), so this is configuration, not code.
- [ ] **Low-rating escalation — half built, and the half that is left is blocked on schema, not on effort.** `survey:escalate` (daily 08:30) mails the sites whose 30-day mean is below a threshold, worst first, through `App\Mail\SurveyEscalationMail`. It is **inert and says so** until both `SURVEY_ESCALATION_MEAN_BELOW` and `SURVEY_ESCALATION_EMAIL` are set — an escalation that fires on a guessed number mails the wrong people every morning. It reuses the reports' minimum-N guard, so a site with two angry answers is never escalated, and it writes nothing: `test_it_writes_nothing_to_the_ops_queue` pins that.
  - 🔴 **The "auto-ticket" half cannot be written honestly yet, and the reason is concrete:** `maintenance_tickets.category` is an **enum** with no `satisfaction` value (it would need a migration, i.e. a domain decision about how a satisfaction complaint is categorised), and `reported_by` is **NOT NULL** — a scheduled command has no user to attribute a ticket to and would have to invent one. Two owner answers unblock it: add the enum value, and name the system account that owns machine-raised tickets.
  - Note: `Mail::raw()` turned out to be untestable — `MailFake::raw()` is a no-op in Laravel 12, so the digest is a Mailable (which also means it is queueable and templateable like the other scheduled mail).
- [ ] **Retention** — responses are personal-adjacent (comments, IP hash). Proposed 24 months then aggregate-only; same decision shape as Plan#1 → F7's audit window and should be made once, consistently. Note the free-text remarks are now also copied into generated PDFs and CSVs, so a PDF in someone's inbox is a second copy with a longer life than the row.
- [x] **`ReportAnalytics` survey block** — done 2026-09-27 (see S8 above).
- [x] **`sites.cms_provider` free-text split** — done 2026-09-27 as part of Plan#4 (see below). The provider rollup now groups by the registry's canonical provider, so the 1-site `IT Business Solutions` row is folded into its 139-site sibling instead of disappearing beside it.

### Open questions still needing owner input

1. **Entry point in the field** — QR printed at each site, or is a portal already showing a splash page?
2. **Question set ownership** — DICT or us? v1 seeded with 4 questions as a starting point.
3. **Anonymity** — confirm nobody wants a name/contact field (adding one changes retention and consent entirely).
4. **Does the rating surface anywhere public?** Same security call as the deferred public map (Plan#7). v1 is internal-only.
5. **Low rating → auto-ticket?** The **notification** half is built and inert (`survey:escalate`, needs a threshold + recipient). The **ticket** half needs two schema answers first: a `satisfaction` value in the `maintenance_tickets.category` enum, and which system account owns machine-raised tickets (`reported_by` is NOT NULL).
6. **Retention window** for responses.

---

## Plan#4 — Multi-provider / multi-NMS

**Status: 🟡 The registry and the normalize command are built (2026-09-27). The NMS binding is still blocked on the owner decision at the bottom — and that decision is a real one, not a formality.**

Owner note: *"the sites has different providers and different NMS."* Measured against the local dataset (1,132 sites) rather than assumed — and the schema does not currently express either fact in a way code can act on.

### What the data actually says

| Column | Rows | Distinct | Values (verbatim in the DB) |
|---|---|---|---|
| `cms_provider` | 1,120 | **12** | Converge ICT Solutions, Inc. · DICT · Data Lake Inc. · EBIZolution · **IT BUSINESS SOLUTIONS** · **IT Business Solutions** · Kingred Network Solutions · Limitless Tech Solutions, Inc. · PHILCOMSAT · Revlv Solutions, Inc. · Smartlink Network Solutions · We Are IT Philippines Inc. |
| `link_provider` | 1,120 | **12** | same list **plus Innove** — and 2 rows differ from `cms_provider` |
| `isp_provider` | 185 | 6 | DICT · Data Lake Inc. · Kingred · PHILCOMSAT · Smartlink · We Are IT |
| `last_mile_tech` | 1,120 | 4 | FIBER · LEO · RADIO · VSAT |
| `source_of_bw` | 185 | 3 | *Direct to Internet · DICT FPIAP : Tuguegarao · Relay : Palusao |

Cross-tab (the reason this matters): PHILCOMSAT runs **280 LEO + 108 VSAT**; DICT itself runs **325 RADIO + 16 LEO + 8 FIBER**; everything else is single-technology. **Transport differs per site, and the provider is what determines it.**

### The two concrete blockers this exposes

**(a) There is no provider/NMS master list — only free text.** `StoreSiteRequest` validates `isp_provider` as `nullable|string|max:100`; nothing constrains values. The data shows the consequence already: **`IT BUSINESS SOLUTIONS` and `IT Business Solutions` are the same provider counted twice**, and `isp_provider` is populated for only 185 of 1,132 sites while `cms_provider` is populated for 1,120. Any per-provider reporting, filtering or routing built today would silently split one provider into two and quietly omit 947 sites from the `isp_provider` dimension. This is the same drift class as the site dedupe and the `config/psgc.php` deletion — **one fact, one source**. → **the first half of this is fixed** (see below); the NMS half is (b).

**(b) `NmsClient` is single-bound and cannot represent the fleet.** The interface takes `array $siteCodes` and returns one flat collection (`NmsClient.php:23`) — fine for one NMS, impossible for twelve providers with different transports (LEO terminal APIs, VSAT gateways, MikroTik/RADIUS for RADIO, OLTs for FIBER) and different credentials. `nms:pull` also polls **every active site in one call** (`NmsPull.php` query has no provider filter), so binding one client would send all 1,132 codes to one vendor's API.

### What got built (2026-09-27) — the two halves that needed no owner input

The provider *decision* is about NMS polling scope. The provider *registry* is not — it is a lookup of company names, and the data to build it was already measured above. Leaving it unbuilt meant the satisfaction report shipped this morning was splitting one company in two.

- [x] **Provider registry** — `config/providers.php`, not a `providers` table, and the reason is this repo's own lesson: nothing creates or edits a provider (no admin surface, no owner workflow), so a table would be a second copy of a list that a file already holds — which is exactly why `config/psgc.php` was deleted. Same role and shape as `config/site_types.php`. Each entry has a stable `code`, the canonical `label`, `aliases`, and `default_transport` measured from the dataset (DICT → RADIO 325/16/8, PHILCOMSAT → LEO 280/108, Converge → FIBER 39, everything else single-tech). `App\Services\ProviderRegistry` resolves a raw string to a code, case- and whitespace-insensitively, ignoring a trailing "Inc."/"Ltd.".
  - **Exactly one alias exists in the file**, and it is the measured split: `IT BUSINESS SOLUTIONS` (139 sites) → `IT Business Solutions`. Spelling variants nobody has recorded yet are *not* guessed at — `providers:normalize` reports them instead, and a new one appearing in the next workbook is a one-line config edit with a test.
  - **No `nms_driver` key, on purpose.** The honest value for all twelve is "unknown", and twelve nulls would look like data and invite someone to read it as "no NMS". That key appears when the owner answers the question below, carrying a real driver name.
- [x] **Backfill + normalize** — `php artisan providers:normalize`, an artisan command and not a migration for the same reason `sites:backfill-regions` is one (migrations run before the workbook import, so a migration would normalize zero rows and the import would write the dirty values straight back). **Dry run by default**, `--apply` to write, idempotent. Measured on the local dataset: *would normalize 140 of 1,120 sites; every provider value in the dataset maps to the registry* — the 140 are precisely the `IT Business Solutions` pair. Originals are stashed in `sites.metadata.provider_raw` for audit, and unrecognised values are reported and left untouched. `ProviderRegistryTest` (9 tests) covers resolution, fail-closed behaviour, dry-run, idempotency and the unmapped report.
- [x] **The satisfaction rollup is fixed at the source.** `SiteSurveyAnalytics::byProvider()` now groups by the registry's canonical provider, so `summarize()` computes the mean over the merged answers — counts add, they are not averaged. The raw spelling stays on the response row (it is history); only the report is folded. Pinned by `test_provider_rollup_folds_case_variants_of_the_same_company`.

### Still open

- [ ] **Per-provider NMS driver binding** — resolve `NmsClient` per provider (a driver map keyed on `nms_driver`) instead of one global binding; `nms:pull` fans out per provider, each with its own credentials, timeout and partial-failure handling. A provider that is down must not stall the others — the current `handle()` loop is one transaction, so one throwing client would roll back every provider's ingest.
- [ ] **Name the owner-facing decision**: which providers are actually in scope for live polling, and for each one — API type, endpoint, auth, and whether it exists yet. Providers without a machine interface stay heartbeat/manual forever, and the plan should say so rather than imply full coverage.

> The registry was worth building ahead of that decision precisely because it does not depend on it. The NMS binding is a different kind of work: it is guessing at twelve vendors' APIs, and a wrong guess produces a plausible-looking integration against an endpoint that does not exist. That stays a question for the owner, same class as Plan#7.

**Also tracked as:** backlog item "Live NMS polling" — bind a real SNMP/REST `NmsClient` and schedule `nms:pull` (needs a reachable NMS/gateway). Reports keep using `site_daily_statuses` until then. **Not one NMS** — the single-bound interface cannot express the fleet as it actually exists.

---

## Plan#5 — Analytics PDF reports

**Status: 🟡 Phases 1–4 done, plus a fifth section in the family. Phase 5's code is complete; the SLA *number* is blocked on DICT, not on engineering.**

Audience: **both** — one-page executive rollup, then detailed ops annexes in the same PDF family. Uptime formula stays `UP / (UP + DOWN + NO_NMS + DOWN_SERVER)` in `config/daily_status.php`. Keep DomPDF; charts as HTML/CSS bars (no Chart.js — DomPDF cannot run JS). Prefer **one combined PDF** with selected sections in `params.sections`.

### Phase 1 — Shared kit + scoped analytics [done]
- [x] `ReportAnalytics`: period (`from`/`to`, default 7d) + geo/project filters; KPIs = site mix, daily-status mix, uptime, trend, coverage, fleet, DOWN episodes, alerts, tickets
- [x] PDF partials (`resources/views/reports/partials/`): cover (scope line, user, timestamp), KPI strip, CSS trend bars, numbered footer
- [x] `GenerateScopedReportRequest`; persist period + geo in `report_exports.params`

### Phase 2 — Complete the four existing PDFs [done]
- [x] **Project:** exec KPIs + annex site register (type, daily status, devices, CIR) + DOWN episodes/tickets
- [x] **Province:** municipality rollup (sites, UP, UP %); project filter printed on the cover scope line; daily status in the site list
- [x] **Site type:** coverage % bars; silent 200-row appendix cap dropped (chunked, uncapped); `site_type` + `status` on the form
- [x] **Barangay:** totals footer follows the scope (REGION II — TOTAL only unfiltered); uncovered-barangay annex when municipality is set

### Phase 3 — New packs (existing data) [done]
- [x] `ops_period` — period health vs previous; site-days + open DOWN episodes
- [x] `fleet` — deployed/stock/repair, warranty ≤90d, firmware vs `APPROVED_FIRMWARE`; device register
- [x] `incidents` — alert severity, ticket backlog, MTTA/MTTR; open lists
- [x] `progress` — weighted accomplishment %; overdue milestones

### Phase 4 — Reports builder UI [done]
- [x] Report builder card: period presets (7/14/30d) + `GeoFilterFields` + section checkboxes + one Generate → `combined` pack (`ops_period`, `fleet`, `incidents`, `progress` in one PDF via `params.sections`)
- [x] CSV companion for every single-pack annex table (regenerated live, `reports.csv`; combined packs excluded — no single table)
- [x] Paginated export history (10/page) with scope line on each row; flash success already shown

### Phase 5 — After product input (do not block 1–4)
- [x] **SLA vs target — the code path is built; only the number is missing (2026-09-27).** `SLA_UPTIME_TARGET` (a percentage) is read in `config/monitoring.php` and flows into `ReportAnalytics::for()` as `sla_target` + `sla_met`, then into the period-health table and the project summary as a PASS/FAIL row. **The target is deliberately unset**, because no figure has been confirmed by DICT and inventing one is the failure mode this plan exists to prevent: with no target the narrative says *"No uptime SLA target is configured, so this period is not marked pass or fail"* rather than printing a verdict against a number nobody agreed to. `is_numeric()` guards a typo in the env value. Both branches are pinned (`test_ops_states_a_verdict_only_once_a_target_exists`).
  - **Still needed from DICT:** the target figure, and whether `NO_NMS` counts against it. The second question is not a new decision — it is `config/daily_status.php` → `observed`, and every caller (uptime, trend, SLA) already follows that one list, so removing a status from it moves the status out of the SLA denominator everywhere at once. That is the answer to "one fact, one source" applied to the SLA.
- [x] `reports:scheduled` monthly provincial pack + mail/Telegram recipients (done 2026-09-13 — previous-month province packs, skip-guard + `--force`, `REPORT_SCHEDULED_EMAIL` → `WATCHDOG_EMAIL` fallback, Telegram when configured, `ScheduledReportsTest`)
- [ ] Solar / GB-delivered sections only once probe data is populated. `solar_w` is captured by the heartbeat today but almost never sent, so the section would print a column of zeros. Nothing to build until the probes report it.

Build order: analytics + kit → enrich the four PDFs → `ops_period` + `fleet` → builder UI → incidents/progress → SLA/schedule.

---

## Plan#6 — Operational readiness gaps

**Status: 🟡 8 of 11 closed.** Found by sweeping for what is *absent*, not what is broken (2026-09-14). Ordered by value/effort.

| # | Gap | Evidence | Fix |
|---|---|---|---|
| 1 | **Error monitoring is installed but inert** | `sentry/sentry-laravel ^4.27` in `composer.json`, `Sentry\Laravel\Integration` wired in `bootstrap/app.php`, providers auto-discovered — but `SENTRY_ENABLED=false` and `SENTRY_LARAVEL_DSN=` empty in both env examples. Production errors currently go nowhere | ⏳ set the DSN (no code change) — also Plan#1 → F3 |
| 2 | **No committed queue-worker supervision** | Scheduler has 14 entries, but no Supervisor/systemd unit in the repo. A dead worker leaves reports/imports `PENDING` forever — the amber stale-queue banner is the only signal | ✅ done 2026-09-13: `deploy/fpiap-worker.conf` Supervisor unit + `queue:check` liveness (stuck-PENDING → exit 1, for cron/monitors; `QueueCheckTest`), wired in `docs/DEPLOY.md` §3 |
| 3 | **No external uptime monitoring** | `/up` health endpoint exists, but nothing pings it | ⏳ `/up` now **fails** on an unreachable database or an unwritable `storage/` (`App\Listeners\CheckSystemHealth`, `HealthCheckTest`), so a monitor pointed at it is a real availability signal rather than a PHP-alive ping — but the monitor itself still has to be created. Owner. Also Plan#1 → F3 |
| 4 | **Backup restore has never been rehearsed** | Nightly to two encrypted destinations + `backup:monitor` at 08:00, but no restore has ever been run | ⏳ quarterly drill (`docs/DEPLOY.md` §5.6) — also Plan#1 → F4 |
| 5 | **No browser/E2E test** | Only PHPUnit + Vitest unit. The two shipped "blank page" and "500" bugs were invisible to both — a render smoke test is the missing layer | ✅ cheap layer done 2026-09-13 (`PageSmokeTest`: `/`, `/daily-ops`, `/map`, `/reports`, `/wallboard` assert 200 + component). Full Playwright remains optional — needs browser binaries + a JS-capable CI runner |
| 6 | **PDF content is not asserted** | `ReportExportTest` checks the `%PDF` magic bytes and `DONE` status, never the numbers — the `$bullets` undefined-variable outage proved template bugs are real | ✅ done 2026-09-13 (`ReportContentTest` renders the incidents/progress/fleet Blades to HTML with seeded data and asserts key figures + bullets land on the page) |
| 7 | **PHPStan level 6** | Level 5 is clean; level 6 measured **199** errors on 2026-09-14 | 🟡 incremental slice done 2026-09-13: all 95 missing return/param types across Http/Console/Models/Policies/Observers/Mail/Jobs (native types, suite still green). Remaining 199 are docblock generics + Services array-shapes — a separate shaped-data pass, still deferred |
| 8 | **No operator manual** | `docs/DEPLOY.md` is ops-only. Encoders/managers using Daily Ops, approvals and reports have no guide | ✅ done 2026-09-13: `docs/USER_GUIDE.md` (Daily Ops lifecycle, map, reports builder + scheduled pack, alerts/tickets, accounts/probe tokens) |
| 9 | **417 soft-deleted duplicate sites** | `sites:dedupe` keeps them deliberately, but no retention policy is written down | ✅ decided 2026-09-13: **retain** — `HeartbeatController` resolves stale AP codes via the trashed rows' `metadata.merged_into`; deleting them turns old codes into 404s. Negligible storage; revisit at 10× growth |
| 10 | **The map loads Leaflet from a public CDN with no SRI** | `app.blade.php:14–18` pulls Leaflet 1.9.4 + markercluster 1.5.3 from `unpkg.com`; **not in `package.json`** (so never bundled, never lockfile-pinned, invisible to `npm audit`); no `integrity=` anywhere in `resources/` | ✅ done 2026-09-14: `leaflet@1.9.4` + `leaflet.markercluster@1.5.3` bundled via npm (pinned, audited — 0 vulns), CDN tags dropped, pure helpers split to `mapHelpers.js`. See Plan#1 → F2 |
| 11 | **Nothing captured the served user's own experience** | Every web route lived inside `Route::middleware(['auth'])` (`routes/web.php:20`) — there was **no unauthenticated route in the app**. Uptime, coverage and fleet were all measured operator-side; a site could report UP all month and still be unusable | ✅ addressed 2026-09-22 by **Plan#3** (per-site user survey) |

---

## Plan#7 — Owner-input backlog (not scheduled)

**Status: ⚪ No spec exists — building blind means rework.** These need an owner decision before any code is written.

- [ ] **Field inspection form** — proposed: `site_inspections` (site, inspector, visited_at, condition good/degraded/critical, findings text, optional auto-ticket on critical). Confirm fields + who may file (encoders? managers?) and whether photos are in scope (storage + moderation cost).
- [ ] **Public unauthenticated map** — publishing exact coordinates of government infra is a security call. Proposed: barangay-aggregated bubbles only (counts + dominant health, no markers/popups/site names), cached, rate-limited. Confirm aggregation level + whether DOWN sites may show publicly. (Related: Plan#3 open question 4.)
- [ ] **SMS** — if Telegram is not enough (ClickSend/Twilio), beside `App\Services\Telegram`.
- [x] **SLA target** — the *code* is done (Plan#5 Phase 5); only the figure is outstanding, plus the `NO_NMS` denominator question, which resolves through `config/daily_status.php`.
- [ ] **Ticket category for satisfaction escalations** — `maintenance_tickets.category` is an enum with no `satisfaction` value, and `reported_by` is NOT NULL. Two answers unblock the auto-ticket half of `survey:escalate` (Plan#3): what the category should be called, and which account machine-raised tickets are attributed to.
- [ ] **TOTP hard-require rollout** — for admins/approvers; needs a rollout so existing accounts are not locked out.
- [ ] **Live NMS bind** — see Plan#4 (needs a reachable NMS/gateway per provider).
- [ ] **Backup restore rehearsal** — see Plan#1 → F4.
- [ ] **Solar power analytics** — sparse `solar_w`; wait for probe data.

---

## Completed (archive)

### Done

#### Hardening (production baseline)
- [x] Scoped RBAC (`can:` + policies), transactional writes, MySQL-safe SQL
- [x] Audit log redaction + payload caps, throttled auth routes, random `setup.ps1` admin password
- [x] Queued Excel imports (atomic per row) and queued PDF reports with tracked exports
- [x] CI: GitHub Actions, PHPStan + larastan **L6** (raised 2026-09-27 from L5 once the shaped-data pass closed the last 202 errors), Pint, ESLint (`--max-warnings=0`), PHPUnit on SQLite **and** `mysql:8.0`

#### Data platform
- [x] Region II workbook importer (`php scripts/import-region-workbook.php`)
- [x] Schema aligned with workbook (classification, providers, lifecycle, NO_NMS / DOWN_SERVER)
- [x] Local data loaded: **1,132 sites** (after dedupe — the workbook lists one row per AP, which had created 1,132 rows for the same locations) · 253 AP devices · 14,270 day records
- [x] `config/site_types.php` labels (PES, PHS, LGU-BRGY, …)
- [x] `App\Support\NameNormalizer` (Ilagan City / City of Ilagan / Basco Capital)

#### Daily status operations
- [x] Daily Ops Board (`/daily-ops`)
- [x] Heartbeat API (`POST /api/heartbeat`, Sanctum probe tokens); 409 on LOCKED
- [x] `statuses:remind` (07:00) · `statuses:snapshot` (23:00 NO_DATA) · `alerts:down` (15 min)

#### Visibility & ops
- [x] Dashboard trends + uptime % · NOC wallboard (30s reload, counters, down list, 14-day bars)
- [x] Sites search/filters including “Down today”
- [x] Maintenance tickets · probe tokens · warranty digest
- [x] Spatie backups (02:15) · report/import cleanup jobs
- [x] `nms:pull` command + `NmsClient` contract (no live SNMP/REST bind yet — see Plan#4)

#### User administration
- [x] Create/edit users, role + project scope, deactivation at login, `user:make`

#### Branding & UX
- [x] FPIAP rebrand (login, sidebar, wallboard, labels, PDF footers, mail, `APP_NAME`)
- [x] Sites/Projects row-click; Projects create button removed
- [x] `latest_daily_status` / `active_deployments` payload casing
- [x] Ziggy relative routes + `ASSET_URL`
- [x] DataTable (Sites / Devices / DailyGrid)
- [x] A11y: mobile drawer focus trap + Escape + restore, aria-live ops counter, sr-only captions

#### Dashboard v2 (2026-09)
- [x] Counters: active sites, UP/DOWN/no-data today, reporting progress (x/y + bar), uptime 7d
- [x] Panels: 14-day trend, barangay/site-type coverage snapshot, field equipment (deployed/stock/repair/warranty), network reach + per-province bars, currently-DOWN episodes with duration, active alerts feed, recent imports, quick actions

#### Data quality — site dedupe (2026-09)
- [x] `sites:dedupe` (dry-run default, `--apply`): merges rows sharing coordinates + normalized name; re-homes deployments/daily statuses (unique-day aware)/accomplishments/tickets/status events/alerts/metrics onto the canonical row, soft-deletes the duplicate with `metadata.merged_into`
- [x] **Result: 1,132 rows → 716 sites**; 124 deployments re-homed (253 intact), 3,733 same-day duplicate rows dropped, 64 sites now correctly hold multiple units
- [x] Heartbeat resolves a merged duplicate's AP code to the canonical site
- [x] Map deployed layer: one aggregated marker per site with device count + roster in popup

#### Alerts console (2026-09)
- [x] `/alerts`: active/resolved lists with severity filter, acknowledge + resolve actions (`daily.approve`), live counters
- [x] Rules CRUD on the same page (`users.manage`)

#### Edit flows (2026-09)
- [x] Site details editor on Site Show (`sites.edit`, project-scoped): identity, geo, classification, status, ISP/last-mile/CIR, coordinates
- [x] Unit editor on Device Show (`devices.edit`): identity/MAC/firmware, condition, status with site re-assignment (closes old deployment, opens new), procurement + warranty fields

#### Site equipment management (2026-09)
- [x] Attach equipment on Site Show: **Assign from stock** or **Register new unit** (asset tag, serial, model, MAC, firmware, role, install date) — creates the unit and opens its deployment atomically (`devices.create`)
- [x] **Detach** from the Installed Equipment table — closes the deployment, returns the unit to stock (`devices.edit`)

#### Security & monitoring (2026-09)
- [x] 2FA TOTP (`App\Support\Totp`), Profile QR (`users.manage`), `/two-factor-challenge`
- [x] Telegram DOWN alerts (`TELEGRAM_*`) alongside email; `last_alerted_at` fillable
- [x] `device_metrics` time-series + 48h sparklines + `metrics:prune` (03:00)
- [x] `device_metric_hourlies` + `metrics:aggregate` (hourly)
- [x] `site_status_events` (heartbeat opens/closes `heartbeat_lost`)
- [x] `alert_rules` + `alerts` + `alerts:evaluate` (every 5 min); seeded: offline >10 min, latency >150 ms / 30 min, battery <11.8 V, bandwidth >85% CIR; email + Telegram; auto-resolve
- [x] `docs/DEPLOY.md` + `deploy.sh` mysqldump preflight
- [x] Firmware-age rule (`firmware_outdated` vs `APPROVED_FIRMWARE` config; info severity)
- [x] Wallboard active-alerts feed (severity-colored, latest 8)
- [x] `statuses:snapshot` derives `UP` from heartbeats before falling back to `NO_DATA`
- [x] `sites:attach-psgc` — barangay/province PSGC into `loc_id`/`prov_id`, municipality PSGC into metadata (1,083/1,128 barangay-recorded sites matched)

#### Map geo filters + Site Type coverage (2026-09) — local only
- [x] `legislative_districts` + `sites:backfill-districts` (1,132/1,132 local sites)
- [x] Cascading filters: Province → District → Municipality → Barangay + Project + Site Type + site status
- [x] URL state; `/map/filter-options`
- [x] Deployed-device markers (default, clustered, daily-status color) + All-sites toggle
- [x] Polygons in `storage/app/geo/`: provinces, municipalities, `districts.geojson` (12), `barangays.geojson` (2,197)
- [x] Highlight + fit bounds + click-to-filter: province → district → municipality → barangay
- [x] Boundary matching is name-normalized ("Basco (Capital)" == Basco) + marker-focus fallback when polygons are missing
- [x] LGU holes closed: Cagaban/Cauayan aliases, Uyugan from OCHA barangays (95 LGUs)
- [x] Site Type coverage panel + queued PDF (`/map/coverage`, `/reports/site-type`)
- [x] Tests: `LegislativeDistrictBackfillTest`, `MapGeoJsonTest`, `SiteCoverageTest`

#### Barangay coverage (2026-09)
- [x] Installed vs total barangays (`BarangayCoverageService`, `/map/barangay-coverage`, `/reports/barangay-coverage`)
- [x] `barangay_references` + `barangays:sync-reference` (upsert-only)
- [x] **PSGC reconciliation: 2,311 barangays — exact PSA match** (`barangays:import-psgc`, 2026-07 publication; per province: Batanes 29 · Cagayan 820 · Isabela 1,055 · NV 275 · Quirino 132; every barangay stamped with its PSGC code)

#### Analytics PDF reports (2026-09-11 kit + enriched four)
- [x] Four queued PDF types: project summary, province, site-type coverage, barangay coverage (`ReportController` → `GenerateReport` → `ReportingService` → DomPDF)
- [x] Export tracking: PENDING → PROCESSING → DONE/FAILED, download, retry, 7-day cleanup
- [x] Map “Generate PDF” posts current geo filters to `/reports/site-type`
- [x] Project summary UI: dropdown + Generate (no longer a full-height project list)
- [x] Shared kit: `ReportAnalytics` (period + geo/project scope → site mix, daily mix, uptime, trend, coverage, fleet, DOWN episodes, alerts, tickets) + `reports/partials/` (cover, KPI strip, CSS trend bars, numbered footer, teal lock)
- [x] Project PDF: exec KPIs + site register (type, daily status, devices, CIR) + DOWN episodes/tickets; province PDF: municipality rollup + scope cover + daily status; site-type PDF: coverage bars + uncapped appendix + `site_type`/`status` form fields; barangay PDF: scope-aware totals + per-barangay breakdown per municipality (totals footer no longer repeats a narrowed scope)
- [x] Every PDF opens with an executive summary (`ReportNarrative`: plain-sentence bullets from the page's own numbers, correctly pluralized) before the KPI strip
- [x] Period + geo persisted in `report_exports.params` (`GenerateScopedReportRequest`)

#### Hardening pass (2026-09-08, on `main`)
- [x] Report area filters no longer return empty; coverage includes unspecified site types
- [x] Site region backfill + filter indexes; `sites:backfill-regions`
- [x] Daily-status workflow (`daily.approve` / lock); coverage cache
- [x] Teal ops UI tokens (`accent` / `ink`); `public/build` gitignored
- [x] Duplicate `dashboard` route name removed — `route:cache` allowed

#### Remediation plan (2026-09-10 audit) — all phases done

Skills: Ponytail governs every phase — shortest working diff, deletion before addition, reuse existing services/policies, one runnable check per non-trivial change. `design-taste-frontend` is explicitly not for dashboards/data tables/product UI, so it is limited to small safe auth/form/empty/error-state polish only; no visual redesign.

**Phase 1 — stop unsafe/broken writes [done]**
- [x] Remove dead `daily-statuses.show/update/destroy` and `accomplishments.update/destroy` resource routes; add route regression tests.
- [x] Gate probe-token issuance on an existing daily-write permission; add authorization tests.
- [x] Include `DOWN_SERVER` in `alerts:down`; add regression coverage.
- [x] Allow the seeded `firmware_outdated` alert metric in rule validation; add coverage.
- [x] Remove stale Sanctum middleware class references; verify API/token tests.
- [x] Run PHPUnit, PHPStan, Pint, ESLint, and Vite build.

**Phase 2 — auth/token hardening [done]**
- [x] Public registration off by default (`REGISTRATION_ENABLED=false`); self-registered accounts start inactive pending admin activation (approval queue).
- [x] Email verification decided: admin activation replaces it (internal ops console; verification routes stay harmless, `MustVerifyEmail` not enforced).
- [x] Sanctum token expiry (30d default, `SANCTUM_EXPIRATION`) + rotation hygiene (expired pruned on issuance, monthly `sanctum:prune-expired`); heartbeat-only ability; owner's project scope enforced per beat.
- [x] View permissions + project scoping on map/API reads (`can:sites.view`/`daily.view`, `accessibleProjectIds`, GeoJSON `project_scope`).
- [x] TOTP enrollment for all accounts; confirm/disable throttled (10/min); disable requires a current code. Hard-require for admins/approvers deferred — needs an owner rollout so existing accounts are not locked out.
- [x] Last-admin guards (self + victim, profile + admin console), self-delete/demote/deactivate blocks, token + session cleanup on user deletion.

**Phase 3 — data integrity [done]**
- [x] Every daily-status writer enforces per-project + APPROVED/LOCKED: board, single/batch store, heartbeat (409), NMS pull, imports (skip + report); snapshot only fills missing rows. Policy `update` requires the site's project approver.
- [x] Workbook imports leave APPROVED/LOCKED rows untouched and report the count in the batch log.
- [x] One open deployment per device (row-locked close-then-open); asset-tag allocation retries on unique collision.
- [x] Monthly `audit:prune` (90d default); HTTP + observer audit rows share one `request_id`.

**Phase 4 — reliability/performance/ops [done, backup rehearsal owner-side]**
- [x] Bounds: GeoJSON 10k-feature cap + 5-min cache, chunked PDF queries, ticket/site/stock selectors capped, alert evaluation chunked.
- [x] Scheduler `withoutOverlapping()` everywhere; queue sizing (`tries`/`timeout`/`backoff` per job, `retry_after` 660 > longest job).
- [x] Offsite encrypted backups wired (second destination + archive password); the restore rehearsal itself is owner-side — quarterly steps in `docs/DEPLOY.md` §5.6.
- [x] Split-layout deploy (`PUBLIC_BUILD_TARGET`, pre-migration dump) + cutover checklist (`docs/DEPLOY.md` §5).

**Phase 5 — frontend taste/tests/docs [done]**
- [x] Map popups escape every DB-derived value (`escapeHtml`, stored-XSS shut); map/API error (`role=alert`) and empty states in place.
- [x] ESLint auth exclusion removed (0/0 gated); Vitest added (`npm test`, CI) with a runnable check on the popup escaper. Component/a11y harness deferred — existing a11y (focus trap, aria-live, sr-only captions, keyboard row links) is covered by convention, not automation.
- [x] README/scheduler/permission docs current; tracked scratch files removed; repo hygiene via CI audits.

**Dependency track [done 2026-09-11 — Laravel 12.69.2, `composer audit` + `npm audit` clean, both blocking in CI]**
- [x] Companion updates folded into the upgrade (`maatwebsite/excel`, `phpspreadsheet`, `dompdf`, Guzzle/Symfony, `postcss`, `nanoid` — no isolated churn needed).

#### Reporting accuracy — `Plan_revision` Phase 1 (2026-09-14) [done, verified]
Every published figure now accounts for all four observed statuses, not just UP/DOWN. Before this, `NO_NMS` (1,967 rows) + `DOWN_SERVER` (68) — 19.3% of `site_daily_statuses` — were invisible to uptime and to the 14-day trend.

- [x] `config/daily_status.php` — single source for the status vocabulary (`codes` vs `observed`), so "which statuses count" is never re-guessed per query
- [x] `ReportingService`: `uptimePct()` = `UP / (UP + DOWN + NO_NMS + DOWN_SERVER)`; new `reportedSiteCount()` counts *sites* (not rows) and excludes `NO_DATA`; `dailyTrend()` emits `up` / `down` / `no_nms` / `down_server`; dashboard + wallboard `no_data_today` use it
- [x] **Measured impact: 82.7% → 66.8%** over 2026-01-08 → 2026-08-03 — the old figure overstated uptime by ~16 points
- [x] Wallboard "Sites Down" now matches `SiteController`'s down filter (`SiteStatusEvent::DOWN_STATUSES`), so a `DOWN_SERVER`-only site is no longer absent from the NOC list
- [x] Trend charts (Dashboard + Wallboard) render four stacked segments with a legend; fixed the wallboard's `width` scoping bug that made every bar render as `NaN`
- [x] `StoreDailyStatusRequest` / `BatchStoreDailyStatusRequest` / `BulkDailyOpsRequest` widened from `in:UP,DOWN,NO_DATA` / `in:UP,DOWN,NO_NMS` to `Rule::in(config('daily_status.codes'))`; Daily Ops gains a **SERVER DOWN** button
- [x] `sites.region` + `sites.island_group` backfilled from province — **local coverage 14.3% → 100%** (the command was wired into `deploy.sh` but had never been run locally)
- [x] `district_blank_sites` guard on *both* district-filterable reports (barangay + site type): a district filter matches on `sites.district`, so sites with a blank district silently vanish — the payload now says how many
- [x] Removed an orphaned docblock left above `municipalitiesInDistrict()` by an earlier edit
- [x] Gates: **212 tests / 948 assertions**, PHPStan 0 errors, Pint 235 files, ESLint 0 warnings, Vitest 11, `npm run build` clean

Judgment call (flagged, easy to revert): the province → region/island-group lookup is now **one** source — `Site::REGIONS_BY_PROVINCE` / `Site::ISLAND_GROUP_BY_PROVINCE` — shared by `SiteObserver` (write path) and `sites:backfill-regions` (existing rows). `config/psgc.php` was deleted because it duplicated that mapping; two tables for the same fact is the drift this phase exists to kill. Trade-off: the lookup is Region II only, so expanding the program means one edit, not two.

#### `Plan_revision` Phases 2–7 — independent verification (2026-09-14) [verified]

Re-checked every claim in `Plan_revision.md` §3 against the tree instead of trusting the ✅ annotations. **Phases 2–6 hold up as written — no false claims found.** Evidence:

- **Phase 2 — authorization & audit: ✅ all 6.** `SiteDailyStatusPolicy` + `SiteAccomplishmentPolicy` exist (auto-discovery binds them); `entry_status` is genuinely absent from `StoreDailyStatusRequest` and guarded by 15 tests in `DailyStatusWorkflowAuthorizationTest` (incl. `test_entry_status_is_not_client_writable`); read routes carry `can:sites.view` / `can:daily.view`; `tokenCan('heartbeat')` enforced; report downloads and both job `failed()` handlers write audit rows; `BACKUP_OFFSITE_DISK` wired in `config/backup.php` + `.env.example`.
- **Phase 3 — performance: ✅ all 4.** All four indexes present in `…_000001` / `…_000002`; `CoverageCache::TTL_MINUTES = 10`; `DeviceController` metrics closure uses `->select()`; `GeoJsonService` projects only the columns the payload uses; `TicketController` restricts assignees to `tickets.manage` holders; device search is anchored-prefix except MAC.
- **Phase 4 — reliability & deploy: ✅ all 6 code items.** `ProcessExcelImport` `tries=3` / `timeout=600` / `backoff=60` + `failed()`; `deploy.sh` has the maintenance window, `trap` rollback, pre-migration dump, `--pull` flag and scoped permissions; single `dashboard` route name with `/dashboard` 301; `public/build` untracked; `Queue::failing` hook. **`config:cache`, `event:cache`, `route:cache`, `view:cache` all run clean** — the deploy path is safe to run.
- **Phase 5 — frontend: ✅ all 5.** `accent`/`ink` tokens in `tailwind.config.js`; **grep confirms zero brand literals outside `theme.js`**; shared `Pagination.vue`, `Dropdown.vue`/`DropdownLink.vue` gone, `ToastStack.vue` alive; ESLint 0/0; `reports.retry` route + `describeScope()` scope lines.
- **Phase 6 — gates: ✅ 1–4.** larastan `^3.10` loaded via `phpstan/extension-installer` (auto-included), level 5, 0 errors; 6 factories + `HasFactory` on 6 models; Pint 235 files; `.github/workflows/ci.yml` present.
- **Phase 7: ✅ correctly marked owner-blocked** (NMS endpoint, SLA target, SMS provider).

**Corrections applied to `Plan_revision.md`:**

| # | Issue | Action |
|---|---|---|
| 1 | Phase 6.5's "untested" list is stale — 2FA disable, device label/scan and `/api/sites` are all covered now | list corrected |
| 2 | Phase 7.4 says "README drift fixed (says Laravel 11 now)" — README says 12 and the framework is 12.69.2 | wording corrected |
| 3 | §2 low-severity findings were never claimed fixed and are **genuinely still open** | logged in Plan#6 |

**Out of scope** (not started, not promised): nationwide shapefiles, live GPS/NMS coordinates, changing Site Type codes, replacing Leaflet.

#### Backlog items closed
- [x] **Firmware fleet UI** (2026-09-13) — `firmware=outdated` filter + counter on Devices (`DeviceFirmwareFilterTest`), same null-semantics as the fleet pack (counter hidden when `APPROVED_FIRMWARE` unset); filter combos now preserved across toggles.
- [x] **Map marker glow-up** (2026-09-13, spec was `Plan_ui.md` Slices 7–9) — status cluster bubbles with counts + dominant % (custom `iconCreateFunction`, halo CSS, no new deps), CARTO light basemap, live legend chips (UP / DOWN / NO_NMS / NO_DATA), merge/detail slider.
- [x] **`Plan_revision` §2 leftovers — all four closed 2026-09-13** (re-verified in tree 2026-09-14). Small, self-contained:
    - [x] `UserController.php:24` — `(bool) $request->input('status')` made `?status=false` truthy. Now `$request->boolean('status')` (`RequestHardeningTest`).
    - [x] `Api/SiteApiController` — `per_page` validated/capped at 200; `show()` history capped at latest 90. Same cap extended to `Api/DailyStatusApiController` (identical hole next door) — covered in `RequestHardeningTest` + `ApiCoverageTest`.
    - [x] `Api/HeartbeatController` — concurrent first-beats serialized with `lockForUpdate()` inside the existing transaction (duplicate-key 500 gone); double-post keeps one row (`RequestHardeningTest`).
    - [x] `.env.production.example` — `CACHE_PREFIX=freewifi_monitor`, sessions + cache to `file` (single VPS); queue stays on `database` for worker visibility.
- [x] **Two endpoints with no test coverage** — done 2026-09-13 (`ApiCoverageTest`): `probe-tokens.destroy` (own revoke works, another user's id → 404) and `/api/daily-statuses` + `/api/daily-statuses/site/{site}` (filters, scoping, per_page cap).

#### Execution plan — R1–R4 (2026-09-11) [all done]

Skills: Ponytail governs — reuse `ReportAnalytics`/partials/`GenerateScopedReportRequest`, one runnable check per pack, gates green before each commit.

- **R1 — incidents + progress packs [done]** — verified 2026-09-13 in tree: `reports.incidents`/`reports.progress` routes, `ReportController@incidentsPdf|progressPdf`, `ReportingService::incidentsData|progressData`, combined `sections`.
- **R2 — builder UI + combined PDF [done]**
- **R3 — Laravel 11 → 12 upgrade [done 2026-09-11]** — Framework now 12.69.2 (`laravel/framework: ^12.0`, companions resolved, `composer audit` clean — CVE-2026-48019 + signed-URL advisory gone); Pint normalizations from the new fixer version; CI `composer audit` is blocking again.
- **R4 — cutover readiness [done 2026-09-11, cutover itself owner-side]** — `deploy.sh` reviewed: maintenance window + trap rollback, pre-migration dump, caches verified, scoped permissions, `queue:restart`; `sites:backfill-regions` (idempotent) runs post-migrate. Frontend rebuilt on the final tree; dev-server smoke 200/200 on Laravel 12. Handoff = `docs/DEPLOY.md` §5 + tag the release before `--pull` deploy.

#### Verification & operability slice (2026-09-27)

The "remaining items" pass, in the order they were worth doing. Every claim below is re-checked against the tree, not taken from a marker.

- [x] **PHPStan level 6 → 0 errors, gate raised to L6** (see Plan#1 → F5 for the full account). Shared payload shapes are now declared once beside the service that builds them and imported by the readers, so a renamed key is a build failure rather than a blank cell in a PDF.
- [x] **Two real defects the type pass exposed**, both in numbers people forward: the project-summary PDF's two coverage cells rendered `0 / 0` because they read keys no service produces, and `SiteSurveyAnalytics` computed `response_rate` against a hardcoded 30-day denominator whatever window it was asked for. Both fixed, both pinned by tests with non-zero fixtures.
- [x] **`docs/INCIDENT_RUNBOOK.md`** — the "an alert fired, now what" page (Plan#1 → F6): both alert paths, all five seeded rules with a first diagnostic, who gets notified, a stuck-queue runbook, a dead-console runbook, log retention, notifier degradation. Its escalation table is deliberately left for the owner to fill rather than invented.
- [x] **Drills turned into runnable checks** — `tests/Feature/OperationalDrillTest.php` (7 tests): a dead worker leaves `FAILED` + audited + retryable, the stuck-PENDING signal `queue:check` exposes, and — with every notifier unreachable — pages still serve 200, the public survey still records a response, and `alerts:evaluate` still completes with the alert recorded.
- [x] **Log retention decided and implemented** — `logs:prune` (30 days, scheduled 03:20), with the logrotate alternative written, rejected on the merits, and recorded here so nobody re-adds it.
- [x] **User satisfaction shipped end to end** (Plan#3 → S8) — a fifth report section with CSV + a builder checkbox, a User rating column on Sites index, and a printable QR placard sheet for the field.
- [x] **SLA pass/fail built, target left unset** (Plan#5 → Phase 5) — with no confirmed figure the reports say so out loud instead of inventing a verdict.
- [x] **Provider registry + `providers:normalize`** (Plan#4) — twelve companies instead of twelve-plus-one spellings; the satisfaction rollup is fixed at the source rather than cosmetically relabelled. Measured: 140 of 1,120 sites, every value recognised.
- [x] **`survey:escalate`** — the low-rating digest, inert until a threshold and a recipient exist. A mailable, not `Mail::raw`, because `MailFake::raw()` is a no-op in Laravel 12 and an untestable notification is an unverified one.
- [x] **`/up` made worth monitoring** — it now fails on an unreachable database or an unwritable `storage/`, so the external monitor the owner still has to create is pointed at something that means "available" rather than "PHP answered".
- [x] **Survey copy no longer lies** — `Survey/Form.vue` counted "Four quick questions" as literal text next to a form driven by the seeded question set; the count is derived now, so a v2 question set cannot misdescribe itself on its first screen.

**Still owner-side, and deliberately not faked:** the Sentry DSN, the external monitor itself, the restore drill, the supervisor-restart half of the queue drill, the audit + survey retention windows, the SLA figure, the two schema answers the auto-ticket needs, the per-provider NMS polling scope, and the on-call phone numbers. Each is one line in this file with a checkbox.

---

## Deploy notes (when Plan#2 resumes)

- Copy app + `storage/app/geo` (not into the nginx document root as PHP).
- Sync Vite `public/build` to the domain folder **and** `fpiap-app/public` (not in git).
- `php artisan route:cache` is allowed.
- Run `php artisan db:seed --class=SiteSurveySeeder` (idempotent) — see Plan#3.
- Optionally set `SLA_UPTIME_TARGET` once DICT confirms the figure — see Plan#5 Phase 5. Leave it blank until then; the reports say "no SLA set" rather than guessing.
- Runbooks: `docs/DEPLOY.md` (deploy), `docs/INCIDENT_RUNBOOK.md` (an alert fired), `docs/USER_GUIDE.md` (using the console). `deploy.sh` assumes a standard `public/` docroot — extra copy step required on this CloudPanel split layout.
