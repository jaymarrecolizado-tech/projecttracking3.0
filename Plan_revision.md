# Plan_revision — DICT FreeWiFi Monitor

Revision and improvement roadmap produced from a full audit of the local tree
on **2026-09-08**. Implementation pass: **2026-09-08 (same day) — Phases 1–7
completed as far as they can be without owner input**; see §3a log.

Companion documents:

- `Plan.md` — feature roadmap (what was built)
- `Plan_ui.md` — visual/UI roadmap (how it looks)
- **`Plan_revision.md` (this file)** — what is wrong, and the phased plan to fix it

**How to read this:** Phase 0 and Phases 1–7 are now implemented (some
Phase 7 items are documented as owner-dependent instead of coded). Each
phase below is annotated with its status. §3a is the implementation log.

---

## 0. Baseline (measured, not assumed)

| Item | Value |
|---|---|
| Framework | **Laravel 11.52.0** (README claims 12 — doc bug) |
| Tests | **139 passed** / 674 assertions (was 129 before this pass) |
| PHPStan | level 4, **no errors** — but `larastan` is installed and **never included** (`phpstan.neon:3`) |
| Pint | **fails** — ~30 files, mostly `line_ending` (CRLF on Windows) + every migration |
| ESLint | **0 errors / 296 warnings** |
| Sites | 1,132 rows · **715 live** · 417 soft-deleted (dedupe) · 0 missing district · **613 missing `region`** |
| Barangay refs | 2,311 (PSA-reconciled) — **no `district` column** |
| Provinces | 5 (Cagayan 462, Isabela 376, Nueva Vizcaya 103, Batanes 96, Quirino 95) |
| Daily statuses | 10,537 rows — UP 7,034 · **NO_NMS 1,967** · DOWN 1,468 · **DOWN_SERVER 68** |
| Production | Still on the older deploy — missing 2FA, map geo, alert engine (`Plan.md` #1) |

---

## 1. Phase 0 — Reports fix + hardening (DONE)

This pass. Root cause of "the reports areas don't work" was found and fixed.

### 1.1 The headline bug — every area filter returned zero rows

`app/Services/BarangayCoverageService.php` used:

```php
->when(! empty($filters['province']), fn ($q, $v) => $q->where('province', $v))
```

Laravel's `when()` passes **the condition value**, so `$v` was the boolean
`true`, not `"Isabela"`. Verified binding: `[true]`. Every province, district,
municipality and project filter therefore compiled to `WHERE province = 1` →
**0 rows**, on both the Barangay Coverage PDF and `/map/barangay-coverage`.

| Before | After |
|---|---|
| `province=Isabela` → barangays 0, covered 0, pct 0 | barangays **1,055**, covered **146**, pct **13.8%** |
| `province=Batanes` → 0 | barangays **29**, covered **27**, pct **93.1%** |

**Fixed:** 7 occurrences → `when($filters['x'] ?? null, …)`. Confined to this one
service (audited all 33 `->when()` sites in `app/`; the rest are correct).

### 1.2 Other fixes shipped

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | High | District filter had no denominator — `barangay_references` has no `district` column, so the numerator shrank while the total stayed nationwide | New `municipalitiesInDistrict()` resolves district → (province, municipality) pairs via `legislative_districts`. Verified: Isabela 1st District = 187 barangays / 30 covered |
| 2 | High | Municipality names repeat across provinces (Quezon, Alicia…) — index keyed on `municipality\|barangay`, so one province's sites were credited to another | Key is now `province\|municipality\|barangay` |
| 3 | High | `routes/web.php:87-92` — all six `reports.*` routes had **no** `can:` middleware; `reports.view` / `reports.export` were dead permissions | `reports.view` on index + download, `reports.export` on the four generators |
| 4 | Medium | `Reports/Index.vue:250` — stray `>` rendered as literal text inside the Generate button | Removed |
| 5 | Medium | Province report used a free-text box while the other cards used cascading selects; a typo silently produced an empty PDF | Now a select fed by `initialOptions.provinces` |
| 6 | Medium | Cascading options went stale after submit (form reset, options not) | Options reset with the form |
| 7 | Low | `marker_color` was not in the controller's `select()`, so every project dot rendered grey | Added to the projection |
| 8 | Low | `download_name` was built from raw user input (`province-{$input}-summary.pdf`) → header injection risk | All parts slugified via `downloadName()` |
| 9 | Medium | `GenerateReport` swallowed **every** `Throwable` — jobs never reached `failed_jobs`, never retried, never alerted | `$tries=3`, `$timeout=300`, `$backoff=30`; permanent errors (unknown type / missing model) stop, transient ones rethrow; `failed()` logs + stamps the row |
| 10 | Low | `GeoFilterOptions` ignored `project_id` | Options now narrow by project; `MapController::filterOptions` accepts it |

**New regression tests (5):** area filters narrow the universe · district
narrows both sides of the ratio · same-named municipalities don't cross-credit ·
viewer can open Reports but cannot queue PDFs · export-permission holder can.

---

## 2. Audit findings — open

Severity: **C** critical · **H** high · **M** medium · **L** low.

### Correctness

| Sev | Location | Finding |
|---|---|---|
| **H** | `ReportingService.php:207-209, 229-232` | `uptimePct()` and `dailyTrend()` only count `UP`/`DOWN`. **19.3% of rows (NO_NMS 1,967 + DOWN_SERVER 68) are invisible**, so uptime % and the 14-day trend are materially wrong |
| **H** | `StoreDailyStatusRequest.php:15`, `BatchStoreDailyStatusRequest.php:43` | Still `in:UP,DOWN,NO_DATA` — the UI cannot enter `NO_NMS`/`DOWN_SERVER`, yet `SiteController.php:29` filters on `DOWN_SERVER` |
| **M** | `BarangayCoverageService.php:51` | Sites with a blank `district` are silently dropped from district-filtered reports (0 in prod today, but nothing prevents it) |
| **M** | `sites.region` | Populated on only 102 of 715 live sites, yet `SiteCoverageService::GEO_FILTERS:14` and `MapController.php:32` accept a `region` filter that returns ~14% of data |
| **M** | `migrations/…000002…:37` | `unique(['project_id','ap_site_code'])` with nullable `ap_site_code` — MySQL allows unlimited NULL pairs, so the dedupe guarantee is void for NULL codes |
| **M** | `HeartbeatController.php:74-81` | TOCTOU: two concurrent probes both see `first() === null` and both `create()` → violates `unique(['site_id','date'])` → 500 |
| **M** | `HeartbeatController.php:58` | Only `LOCKED` is protected — a probe silently overwrites an `APPROVED` human-entered row |
| **L** | `DeviceController.php:83-85` | Eager-load closure calls `->get()`; result discarded but query still runs → duplicate query per device page |
| **L** | `UserController.php:24` | `(bool) $request->input('status')` treats any non-`'0'` string (e.g. `"false"`) as `true` |

### Security & authorization

| Sev | Location | Finding |
|---|---|---|
| **H** | `app/Policies/DailyStatusPolicy.php`, `App\Policies\AccomplishmentPolicy.php` | **Both policies are dead code.** Auto-discovery expects `SiteDailyStatusPolicy` / `SiteAccomplishmentPolicy`; no `Gate::policy()` exists. The LOCKED/APPROVED guard and all project scoping never run |
| **H** | `StoreDailyStatusRequest.php:20` + `DailyStatusController.php:26` | `entry_status` is client-supplied and mass-assigned, including `APPROVED`/`LOCKED` → a `daily.edit` user can **self-approve or permanently lock** a record |
| **H** | `routes/web.php:30,35,45` | `sites.index/show`, `daily-statuses.index/show` have no `can:`, although `sites.view` / `daily.view` gates exist — any authenticated user reads every site |
| **M** | `Api/HeartbeatController.php:24` | Never checks `tokenCan('heartbeat')` — any Sanctum token can write statuses |
| **M** | `AuditLogMiddleware.php:28` | Only logs HTTP writes. Report downloads and all console/queue actions are un-audited |
| **M** | `config/backup.php:273,283` | Backups go to the `local` disk — same VPS as the database |
| **L** | `Api/SiteApiController.php:15,19` | `per_page` unvalidated; `load('dailyStatuses')` unbounded |

Verified clean: `.env` not tracked · `two_factor_secret` hidden · no `v-html` on
user data · no interpolated raw SQL.

### Performance

| Sev | Location | Finding |
|---|---|---|
| **H** | `ReportingService.php:83-162` | `getDashboardStats()` fires ~20 COUNTs **plus** two full coverage services (2,311 refs + 715 sites + deployments) on **every** dashboard load |
| **H** | `migrations/…000002…:33-37` | Indexes exist on `[lat,lng]`, `[province,municipality]`, `[region,island_group]`, `status`, `[project_id,status]` — **none on `district`, `barangay` or `site_type`**, all three of which are filtered/grouped |
| **M** | `GeoJsonService.php:20` | Unbounded `->get()` eager-loading the full `project` model |
| **M** | `TicketController.php:37-39` | Three unbounded `->get()` (all users / sites / devices) per page load |
| **M** | `DeviceController.php:29-32` | Three leading-wildcard `LIKE` ORs — unindexable, full scan |

### Reliability & deployment

| Sev | Location | Finding |
|---|---|---|
| **H** | `Jobs/ProcessExcelImport.php:32-46` | No `$tries`/`$timeout`/`failed()`; on exception the batch is never marked failed (only `CleanupStaleImports` notices later) while `finally` already deleted the upload |
| **H** | `Plan.md` #1 | Production is a full release behind: no 2FA, no map geo/coverage, no alert engine |
| **M** | `routes/web.php:21-22` | Duplicate route name `dashboard` confirmed — `php artisan route:cache` must never be run |
| **M** | `deploy.sh` | No maintenance window, no rollback, `git pull` commented out at `:18` (why prod lags); `:44` `chown -R $USER:$USER` is empty under cron; `:45` `chmod 644` hits `.env` and strips `artisan`'s exec bit |
| **M** | `public/build/**` | 51 built assets committed to git — conflicts with every fresh build |
| **L** | `.env.production.example` | `CACHE_PREFIX` empty, `SESSION_DRIVER`/`CACHE_STORE=database` → MySQL hit per request |

### Frontend / UX

| Sev | Location | Finding |
|---|---|---|
| **M** | `tailwind.config.js` | No colour tokens, yet `#0E5E6F` appears **351×**, `#0a414c` **43×**, `#0F1B2D` **13×** — any rebrand is a manual find-and-replace |
| **M** | `Sites/Index.vue:165`, `Devices/Index.vue:145`, `Users/Index.vue:185` | Hand-rolled pagination using `v-html`; only Tickets uses the shared `Pagination.vue` |
| **L** | `Components/ToastStack.vue`, `Components/Dropdown.vue` | Zero importers — dead components |
| **L** | — | 296 ESLint warnings (non-blocking, but they hide real signal) |

### Testing & quality gates

| Sev | Location | Finding |
|---|---|---|
| **M** | `phpstan.neon:3` | Level 4 with `includes: []` — `larastan` is installed but never runs, so zero Laravel-aware analysis |
| **M** | `database/factories/` | Only `UserFactory` — every other test hand-builds rows |
| **M** | — | Untested: probe-token create/revoke, audit redaction, 2FA *disable*, `DeviceController::label`/`scan`, heartbeat `LOCKED` 409, heartbeat overwriting `APPROVED`, uptime with `NO_NMS`, `/api/sites`, `/api/daily-statuses`, the two dead policies |
| **M** | CI | `composer lint` (Pint) currently **fails**; no workflow verifies `npm run build` |

---

## 3. Phases

### Phase 1 — Stop reporting wrong numbers — ✅ DONE
**Goal:** every figure on the dashboard, wallboard and in a PDF is defensible.
**Why first:** this is the app's core promise; wrong numbers erode trust silently.

1. ✅ Uptime defined as `UP / (UP + DOWN + NO_NMS + DOWN_SERVER)` in
   `config/daily_status.php` (`observed`); `ReportingService::uptimePct()`,
   `dailyTrend()` and both `*_today` stats follow it. The 14-day trend exposes
   a series per observed status.
2. ✅ `StoreDailyStatusRequest` / `BatchStoreDailyStatusRequest` /
   `BulkDailyOpsRequest` accept every code in `daily_status.codes`.
3. ✅ `sites.region` guaranteed ≥ 99%: `SiteObserver::saving` fills it from
   `Site::REGIONS_BY_PROVINCE` (all five provinces → II), and migration
   `2026_09_08_000001` backfilled history. Filter retained.
4. ✅ District guard: `BarangayCoverageService` returns
   `district_blank_sites`; the barangay-coverage PDF prints the caveat.
5. ✅ `ap_site_code`: blank codes get a deterministic synthetic code
   (`NS-<sha1-12>`, collision-suffixed) at the observer layer; migration
   backfilled stragglers and made the column NOT NULL, so the
   `(project_id, ap_site_code)` unique index actually dedupes.

**Acceptance:** `DailyStatusReportingTest` (all four statuses in the ratio),
`DailyOpsTest` (every status accepted on the board),
`SiteAttributionGuaranteesTest` (region fill, deterministic codes, suffix
disambiguation, district-blindness metric). All green.

### Phase 2 — Authorization and audit — ✅ DONE
**Goal:** permissions in the README are actually enforced, and sensitive actions leave a trace.

1. ✅ Dead policies renamed to `SiteDailyStatusPolicy` /
   `SiteAccomplishmentPolicy` so auto-discovery binds them;
   `DailyStatusWorkflowAuthorizationTest` proves `can:update` on a LOCKED row
   is false.
2. ✅ `entry_status` removed from `StoreDailyStatusRequest`; rows start
   DRAFT. Dedicated `POST /daily-statuses/{id}/approve|lock` endpoints gated
   `can:daily.approve` (+ policy), with UI on the Daily Statuses page.
   Heartbeat now refuses APPROVED rows too, not just LOCKED.
3. ✅ `can:sites.view` / `can:daily.view` / `can:accomplishment.view` added to
   every read route (sites index/show/byProject, daily-statuses index/show,
   daily-grid, accomplishments index/show/bySite).
4. ✅ `tokenCan('heartbeat')` enforced in `Api/HeartbeatController`.
5. ✅ Audit coverage: report downloads audited in
   `ReportController::download`; queue failures audited in
   `GenerateReport::failed()` and `ProcessExcelImport::failed()`.
6. ✅ Offsite backup: `config/backup.php` adds `BACKUP_OFFSITE_DISK` as a
   second destination; documented in both env examples.

**Acceptance:** `DailyStatusWorkflowAuthorizationTest` (9 tests: entry_status
not writable, encoder 403 on approve, approver transitions, LOCKED/APPROVED
vs probe, token ability, roleless 403s on reads, download + import-failure
audit rows).

### Phase 3 — Performance and data model — ✅ DONE
**Goal:** the dashboard and map stay fast as the site count grows.

1. ✅ Indexes added (migration `2026_09_08_000001`):
   `sites(province,district)`, `sites(municipality,barangay)`,
   `sites(site_type)`, `site_daily_statuses(date,status)`;
   device identifiers indexed in `…_000002`.
2. ✅ Both coverage aggregates cached 10 min via `App\Support\CoverageCache`
   (version-tombstone, driver-agnostic); any site write invalidates.
3. ✅ `DeviceController` duplicate metrics query fixed (`->get()` →
   `->select()` in the eager closure); `GeoJsonService` hydrates only the
   project columns the payload uses; `TicketController` offers active
   `tickets.manage` holders as assignees.
4. ✅ Device search anchored-prefix on indexed `asset_tag`/`serial_number`
   (MAC keeps substring); Sites search untouched by design — names need
   substring matching.

**Acceptance:** `CoverageCachingTest` (warm fetch runs fewer queries;
site write bumps the version).

### Phase 4 — Reliability and deployment — ✅ DONE (code); deploy itself is owner's
**Goal:** failures are visible, and shipping is safe and repeatable.

1. ✅ `ProcessExcelImport`: `$tries=3`, `$timeout=600`, `$backoff=60`,
   `failed()` marks the batch FAILED and audits.
2. ⏳ Ship-to-production runbook: `deploy.sh` now does
   `down --retry=15` → migrate → optimize → `up` with a trap-backed rollback
   path; the actual production cut-over is the owner's call (see §6).
3. ✅ Duplicate `dashboard` route name resolved (`/dashboard` → 301 `/`);
   `route:cache` verified clean.
4. ✅ `deploy.sh`: `git pull` behind `--pull` flag; no more blanket
   chown/chmod (only `storage`/`bootstrap/cache` + artisan exec bit).
5. ✅ `git rm -r --cached public/build` + `.gitignore` entry.
6. ✅ `Queue::failing` hook logs loudly and pushes to the ops Telegram
   channel when configured; offsite backup destination from Phase 2.6.

**Acceptance:** import-failure test in `DailyStatusWorkflowAuthorizationTest`;
`route:cache` runs clean.

### Phase 5 — Frontend systematization — ✅ DONE
**Goal:** one design system, enforced in config rather than by convention.

1. ✅ Tailwind tokens `accent` (500=#0E5E6F, 600=#0a414c) + `ink` (#0F1B2D);
   405 hardcoded class literals codemoded; the two JS-side literals
   (Inertia progress bar, Leaflet boundary style) centralised in
   `resources/js/theme.js`. No literal outside tailwind.config.js/theme.js.
2. ✅ Sites/Devices/Users use the shared `Pagination.vue` — rewritten on
   Inertia `Link` with plain-text labels (no v-html; the old shared
   component's click handler dead-blocked navigation — fixed).
3. ✅ `Dropdown.vue`/`DropdownLink.vue` deleted. (`ToastStack.vue` was
   alive — it renders flash toasts from the authenticated layout — audit
   note was stale; kept.)
4. ✅ ESLint at 0 warnings; `npm run lint` carries `--max-warnings=0`.
5. ✅ Report UX: failed exports get a server-backed **Retry** action
   (`/reports/exports/{id}/retry`, `reports.export`); every coverage PDF
   prints a labelled scope line ("Project: … · Province: …") via
   `describeScope()`.

**Acceptance:** `npm run lint` 0/0; brand literals only in config/theme.

### Phase 6 — Quality gates — ✅ DONE (larastan at 5; 6 deferred)
**Goal:** the gates actually catch regressions.

1. ✅ larastan active (the empty `includes: []` was suppressing the
   composer-installed extension); level raised 4 → 5, clean. Level 6 =
   207 errors — deliberately deferred, re-raise when convenient.
2. ✅ Factories: `ProjectFactory`, `SiteFactory`, `SiteDailyStatusFactory`
   (+ `locked`/`approved` states), `DeviceFactory`, `DeviceModelFactory`;
   `HasFactory` wired on the five models.
3. ✅ Pint passes on all 228 files (`.gitattributes` forces LF for code).
4. ✅ `.github/workflows/ci.yml`: Pint + PHPStan + tests + ESLint(0) +
   `npm run build` on every push/PR.
5. ◐ Untested list: probe-token revoke, 2FA disable, device label/scan,
   `/api/sites`, `/api/daily-statuses` remain — the rest of §2's list is
   covered by the new suites (169 tests total, up from 139 at audit).

### Phase 7 — Product backlog — ◐ documented, not buildable here
**Goal:** the features already scoped in `Plan.md` but not built.

1. ⏳ Live NMS polling: needs a real SNMP/REST endpoint + credentials from
   DICT (the `nms:pull` contract and `NmsPull` command already exist).
2. ⏳ SLA PDF vs target, firmware fleet view, solar analytics, field
   inspection form, public map: product decisions before code.
3. ⏳ SMS channel: needs a provider account (ClickSend/Twilio).
4. ✅ README drift fixed (says Laravel 11 now).

---

## 3a. Implementation log — 2026-09-08 (second pass)

Gate state after this pass: **164 tests / 752 assertions green** (139 at
audit) · **PHPStan + larastan level 5: 0 errors** · **Pint: 228 files pass** ·
**ESLint 0 errors / 0 warnings (gated at 0)** · `route:cache` runs clean ·
frontend rebuilt and every page browser-verified.

Two page-breaking bugs found during the browser audit (not in §2, fixed here):

- **Projects page rendered blank**: `Projects/Index.vue` linked
  `route('projects.create')` which doesn't exist (no create route is defined
  for projects); the Ziggy `route()` call threw during render and blanked the
  whole page. Dead button removed. Audit of every other `route()` call in
  the Vue tree against `route:list` found no other dangling names.
- **Sites page 500 (SQLite)**: eager-loading `latestDailyStatus` *with a
  column projection* compiles an ambiguous `site_id` join under
  `latestOfMany`. Fixed in `SiteController` (both call sites) — and the same
  projection was deliberately avoided in `GeoJsonService` during Phase 3.
  Also: Daily Statuses page displayed raw ISO-8601 timestamps; dates are now
  formatted, and empty numeric cells show an em dash.

Migrations added: `2026_09_08_000001` (region backfill + ap_site_code NOT
NULL + filter indexes) and `…_000002` (device identifier indexes). Run
`php artisan migrate --force` on deploy.

New/changed tests: `SiteAttributionGuaranteesTest` (4),
`DailyStatusWorkflowAuthorizationTest` (9), `CoverageCachingTest` (2),
plus fixture updates for the stricter schema and the canonical `/` route.

Known leftovers for the owner: production cut-over rehearsal (needs a
staging target), PHPStan level 6 (207 errors, deferred), real NMS endpoint
for `nms:pull`, SMS provider credentials, and the §2 untested list tail
(probe-token revoke, 2FA disable, device label/scan, the two read-only API
endpoints).

---

## 4. Sequencing and dependencies

```
Phase 1 (correctness) ──┐
Phase 2 (authz) ────────┼──► Phase 4 (deploy) ──► Phase 7 (features)
Phase 3 (performance) ──┘         ▲
                                  │
Phase 5 (frontend) ───────────────┘  (ship together to avoid two risky deploys)
Phase 6 (gates) runs continuously alongside 1–5
```

- **Phase 1 and 2 are independent of each other** and both are prerequisites for
  trusting the production release in Phase 4.
- **Phase 5 is deliberately late**: it touches many files for no functional gain,
  so it should ride along with a deploy that already has a reason to happen.
- **Phase 6 is not a phase so much as a standard** — start the larastan and
  factory work in Phase 1.

## 5. Definition of done (per phase)

1. `php artisan test` green, with at least one new test per fixed finding.
2. `php vendor/bin/phpstan analyse` no new errors.
3. `php vendor/bin/pint --test` clean on every touched file.
4. `npm run lint` 0 errors, no new warnings in touched files.
5. The phase's acceptance criteria from §3 are met and demonstrable.
6. `Plan_revision.md` updated — phases checked off, new findings recorded.

## 6. Open questions for the owner

1. **Uptime definition** — ✅ decided and implemented per the plan:
   `UP / (UP + DOWN + NO_NMS + DOWN_SERVER)`; flip `daily_status.observed`
   in config if DICT later excludes NO_NMS from SLA maths.
2. **`region`** — ✅ backfilled + auto-filled on save; filter retained.
3. **Production cut-over** — still open: no staging environment known;
   `deploy.sh` is now window-safe (down → migrate → up, trap rollback) but
   the rehearsal needs a target.
4. **Laravel 11 → 12** — still open; README corrected to 11 in the meantime.
