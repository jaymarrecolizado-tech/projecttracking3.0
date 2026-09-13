# Plan — DICT FreeWiFi Monitor

Living roadmap. **Done** = in the local repo. **Open** = not built, or built locally but not on production.

**Current focus: fortify before deploy.** The feature slice is complete and all gates are green, so the remaining risk is concentrated in things that only misbehave *under production conditions* — and that tests currently cannot see. Deployment is deliberately **on hold** until the Fortify workstreams below are done; see backlog #1.

**Production (`fpiapr2.dictr2.cloud`):** still an older tree. GitHub `main` is ahead (`2829662` and later). `route:cache` is now safe (single `dashboard` name; `/dashboard` 301s to `/`).

Companion docs: `Plan_revision.md` (2026-09-08 hardening log; §3b/§3c are the 2026-09-14 verification) · `Plan_ui.md` (visual roadmap).

---

## Done

### Hardening (production baseline)
- [x] Scoped RBAC (`can:` + policies), transactional writes, MySQL-safe SQL
- [x] Audit log redaction + payload caps, throttled auth routes, random `setup.ps1` admin password
- [x] Queued Excel imports (atomic per row) and queued PDF reports with tracked exports
- [x] CI: GitHub Actions, PHPStan + larastan L5, Pint, ESLint (`--max-warnings=0`), PHPUnit

### Data platform
- [x] Region II workbook importer (`php scripts/import-region-workbook.php`)
- [x] Schema aligned with workbook (classification, providers, lifecycle, NO_NMS / DOWN_SERVER)
- [x] Local data loaded: **716 distinct sites** (after dedupe — the workbook lists one row per AP, which had created 1,132 rows for the same locations) · 253 AP devices · 10,538 day records (true distinct days)
- [x] `config/site_types.php` labels (PES, PHS, LGU-BRGY, …)
- [x] `App\Support\NameNormalizer` (Ilagan City / City of Ilagan / Basco Capital)

### Daily status operations
- [x] Daily Ops Board (`/daily-ops`)
- [x] Heartbeat API (`POST /api/heartbeat`, Sanctum probe tokens); 409 on LOCKED
- [x] `statuses:remind` (07:00) · `statuses:snapshot` (23:00 NO_DATA) · `alerts:down` (15 min)

### Visibility & ops
- [x] Dashboard trends + uptime % · NOC wallboard (30s reload, counters, down list, 14-day bars)
- [x] Sites search/filters including “Down today”
- [x] Maintenance tickets · probe tokens · warranty digest
- [x] Spatie backups (02:15) · report/import cleanup jobs
- [x] `nms:pull` command + `NmsClient` contract (no live SNMP/REST bind yet — see backlog)

### User administration
- [x] Create/edit users, role + project scope, deactivation at login, `user:make`

### Branding & UX
- [x] FPIAP rebrand (login, sidebar, wallboard, labels, PDF footers, mail, `APP_NAME`)
- [x] Sites/Projects row-click; Projects create button removed
- [x] `latest_daily_status` / `active_deployments` payload casing
- [x] Ziggy relative routes + `ASSET_URL`
- [x] DataTable (Sites / Devices / DailyGrid)
- [x] A11y: mobile drawer focus trap + Escape + restore, aria-live ops counter, sr-only captions

### Dashboard v2 (2026-09)
- [x] Counters: active sites, UP/DOWN/no-data today, reporting progress (x/y + bar), uptime 7d
- [x] Panels: 14-day trend, barangay/site-type coverage snapshot, field equipment (deployed/stock/repair/warranty), network reach + per-province bars, currently-DOWN episodes with duration, active alerts feed, recent imports, quick actions

### Data quality — site dedupe (2026-09)
- [x] `sites:dedupe` (dry-run default, `--apply`): merges rows sharing coordinates + normalized name; re-homes deployments/daily statuses (unique-day aware)/accomplishments/tickets/status events/alerts/metrics onto the canonical row, soft-deletes the duplicate with `metadata.merged_into`
- [x] **Result: 1,132 rows → 716 sites**; 124 deployments re-homed (253 intact), 3,733 same-day duplicate rows dropped, 64 sites now correctly hold multiple units
- [x] Heartbeat resolves a merged duplicate's AP code to the canonical site
- [x] Map deployed layer: one aggregated marker per site with device count + roster in popup

### Alerts console (2026-09)
- [x] `/alerts`: active/resolved lists with severity filter, acknowledge + resolve actions (`daily.approve`), live counters
- [x] Rules CRUD on the same page (`users.manage`)

### Edit flows (2026-09)
- [x] Site details editor on Site Show (`sites.edit`, project-scoped): identity, geo, classification, status, ISP/last-mile/CIR, coordinates
- [x] Unit editor on Device Show (`devices.edit`): identity/MAC/firmware, condition, status with site re-assignment (closes old deployment, opens new), procurement + warranty fields

### Site equipment management (2026-09)
- [x] Attach equipment on Site Show: **Assign from stock** or **Register new unit** (asset tag, serial, model, MAC, firmware, role, install date) — creates the unit and opens its deployment atomically (`devices.create`)
- [x] **Detach** from the Installed Equipment table — closes the deployment, returns the unit to stock (`devices.edit`)

### Security & monitoring (2026-09)
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

### Map geo filters + Site Type coverage (2026-09) — local only
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

### Barangay coverage (2026-09)
- [x] Installed vs total barangays (`BarangayCoverageService`, `/map/barangay-coverage`, `/reports/barangay-coverage`)
- [x] `barangay_references` + `barangays:sync-reference` (upsert-only)
- [x] **PSGC reconciliation: 2,311 barangays — exact PSA match** (`barangays:import-psgc`, 2026-07 publication; per province: Batanes 29 · Cagayan 820 · Isabela 1,055 · NV 275 · Quirino 132; every barangay stamped with its PSGC code)

### Analytics PDF reports (2026-09-11 kit + enriched four)
- [x] Four queued PDF types: project summary, province, site-type coverage, barangay coverage (`ReportController` → `GenerateReport` → `ReportingService` → DomPDF)
- [x] Export tracking: PENDING → PROCESSING → DONE/FAILED, download, retry, 7-day cleanup
- [x] Map “Generate PDF” posts current geo filters to `/reports/site-type`
- [x] Project summary UI: dropdown + Generate (no longer a full-height project list)
- [x] Shared kit: `ReportAnalytics` (period + geo/project scope → site mix, daily mix, uptime, trend, coverage, fleet, DOWN episodes, alerts, tickets) + `reports/partials/` (cover, KPI strip, CSS trend bars, numbered footer, teal lock)
- [x] Project PDF: exec KPIs + site register (type, daily status, devices, CIR) + DOWN episodes/tickets; province PDF: municipality rollup + scope cover + daily status; site-type PDF: coverage bars + uncapped appendix + `site_type`/`status` form fields; barangay PDF: scope-aware totals + per-barangay breakdown per municipality (totals footer no longer repeats a narrowed scope)
- [x] Every PDF opens with an executive summary (`ReportNarrative`: plain-sentence bullets from the page's own numbers, correctly pluralized) before the KPI strip
- [x] Period + geo persisted in `report_exports.params` (`GenerateScopedReportRequest`)

### Hardening pass (2026-09-08, on `main`)
- [x] Report area filters no longer return empty; coverage includes unspecified site types
- [x] Site region backfill + filter indexes; `sites:backfill-regions`
- [x] Daily-status workflow (`daily.approve` / lock); coverage cache
- [x] Teal ops UI tokens (`accent` / `ink`); `public/build` gitignored
- [x] Duplicate `dashboard` route name removed — `route:cache` allowed

### Remediation plan (2026-09-10 audit)

Skills: Ponytail governs every phase — shortest working diff, deletion before addition, reuse existing services/policies, one runnable check per non-trivial change. `design-taste-frontend` is explicitly not for dashboards/data tables/product UI, so it is limited to small safe auth/form/empty/error-state polish only; no visual redesign.

Phase 1 — stop unsafe/broken writes [done]
- [x] Remove dead `daily-statuses.show/update/destroy` and `accomplishments.update/destroy` resource routes; add route regression tests.
- [x] Gate probe-token issuance on an existing daily-write permission; add authorization tests.
- [x] Include `DOWN_SERVER` in `alerts:down`; add regression coverage.
- [x] Allow the seeded `firmware_outdated` alert metric in rule validation; add coverage.
- [x] Remove stale Sanctum middleware class references; verify API/token tests.
- [x] Run PHPUnit, PHPStan, Pint, ESLint, and Vite build.

Phase 2 — auth/token hardening [done]
- [x] Public registration off by default (`REGISTRATION_ENABLED=false`); self-registered accounts start inactive pending admin activation (approval queue).
- [x] Email verification decided: admin activation replaces it (internal ops console; verification routes stay harmless, `MustVerifyEmail` not enforced).
- [x] Sanctum token expiry (30d default, `SANCTUM_EXPIRATION`) + rotation hygiene (expired pruned on issuance, monthly `sanctum:prune-expired`); heartbeat-only ability; owner's project scope enforced per beat.
- [x] View permissions + project scoping on map/API reads (`can:sites.view`/`daily.view`, `accessibleProjectIds`, GeoJSON `project_scope`).
- [x] TOTP enrollment for all accounts; confirm/disable throttled (10/min); disable requires a current code. Hard-require for admins/approvers deferred — needs an owner rollout so existing accounts are not locked out.
- [x] Last-admin guards (self + victim, profile + admin console), self-delete/demote/deactivate blocks, token + session cleanup on user deletion.

Phase 3 — data integrity [done]
- [x] Every daily-status writer enforces per-project + APPROVED/LOCKED: board, single/batch store, heartbeat (409), NMS pull, imports (skip + report); snapshot only fills missing rows. Policy `update` requires the site's project approver.
- [x] Workbook imports leave APPROVED/LOCKED rows untouched and report the count in the batch log.
- [x] One open deployment per device (row-locked close-then-open); asset-tag allocation retries on unique collision.
- [x] Monthly `audit:prune` (90d default); HTTP + observer audit rows share one `request_id`.

Phase 4 — reliability/performance/ops [done, backup rehearsal owner-side]
- [x] Bounds: GeoJSON 10k-feature cap + 5-min cache, chunked PDF queries, ticket/site/stock selectors capped, alert evaluation chunked.
- [x] Scheduler `withoutOverlapping()` everywhere; queue sizing (`tries`/`timeout`/`backoff` per job, `retry_after` 660 > longest job).
- [x] Offsite encrypted backups wired (second destination + archive password); the restore rehearsal itself is owner-side — quarterly steps in `docs/DEPLOY.md` §5.6.
- [x] Split-layout deploy (`PUBLIC_BUILD_TARGET`, pre-migration dump) + cutover checklist (`docs/DEPLOY.md` §5).

Phase 5 — frontend taste/tests/docs [done]
- [x] Map popups escape every DB-derived value (`escapeHtml`, stored-XSS shut); map/API error (`role=alert`) and empty states in place.
- [x] ESLint auth exclusion removed (0/0 gated); Vitest added (`npm test`, CI) with a runnable check on the popup escaper. Component/a11y harness deferred — existing a11y (focus trap, aria-live, sr-only captions, keyboard row links) is covered by convention, not automation.
- [x] README/scheduler/permission docs current; tracked scratch files removed; repo hygiene via CI audits.

Dependency track [done 2026-09-11 — Laravel 12.69.2, `composer audit` + `npm audit` clean, both blocking in CI]
- [x] Companion updates folded into the upgrade (`maatwebsite/excel`, `phpspreadsheet`, `dompdf`, Guzzle/Symfony, `postcss`, `nanoid` — no isolated churn needed).

### Reporting accuracy — `Plan_revision` Phase 1 (2026-09-14) [done, verified]
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

### `Plan_revision` Phases 2–7 — independent verification (2026-09-14) [verified]

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
| 3 | §2 low-severity findings were never claimed fixed and are **genuinely still open** | logged as backlog #7–8 |

Current gate state (snapshot 2026-09-14, re-measured — the suite keeps growing as parallel sessions land tests): **231 tests / 1,085 assertions** · PHPStan 0 errors · Pint 244 files · ESLint 0/0 · Vitest 11 · `npm run build` clean · `config:cache`/`event:cache`/`route:cache`/`view:cache` all clean. Same suite also green on the `pdo_mysql` driver path (see F1).

Out of scope (not started, not promised this slice): nationwide shapefiles, live GPS/NMS coordinates, changing Site Type codes, replacing Leaflet.

---

## Fortify before deploy (current focus)

Deployment is **on hold by owner decision** — harden first. The feature slice is complete and every gate is green, so the remaining risk is not "unfinished features"; it is things that only misbehave under production conditions and that the current tests structurally cannot catch.

Ordered by "what breaks in production that nothing here can see yet".

> **Re-verified 2026-09-14.** A parallel 2026-09-13 session closed several items that older drafts of this section still listed as open. Everything marked ✅ below was confirmed present in the tree (file + test name given) — **do not redo it**. Everything left as `[ ]` was re-checked and is genuinely still open.

### F1 — Prove it on MySQL *(highest leverage)*

`phpunit.xml` runs the whole suite on **SQLite `:memory:`**, `ci.yml` installs only the `sqlite3` extension, and the local `.env` is SQLite too. **Production is MySQL — so the suite has never been run against the engine the app ships on.**

**Measured 2026-09-14 — the risk is real, but smaller and differently shaped than first estimated.** The suite was run against a MySQL-protocol server (`pdo_mysql`) with MySQL 8's strict modes forced on:

| Run | Engine / `sql_mode` | Result |
|---|---|---|
| Baseline | SQLite `:memory:` | 231 passed / 1,085 assertions |
| MySQL driver | MariaDB 10.4.32, lax | **231 passed / 1,085 assertions** |
| MySQL driver + MySQL 8 strictness | MariaDB 10.4.32, `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` | **231 passed / 1,085 assertions** |

`migrate:fresh` also runs clean on the MySQL grammar. **The predicted fallout did not materialise** — no `ONLY_FULL_GROUP_BY` failures on grouped `selectRaw`, no DECIMAL-as-string or collation failures.

Both driver-specific branches **did execute** under `pdo_mysql`, and both passed:

- `ImportService::nextAssetTag()` — `Phase5FeaturesTest:167` invokes it directly (tags `FW-0001/0007/0002` → expects `FW-0008`), so `MAX(CAST(SUBSTRING(asset_tag, 4) AS UNSIGNED))` genuinely ran. The earlier claim that "the MySQL expression has never executed in any test" is **false as of this measurement**.
- `BackfillSiteDistricts` — `LegislativeDistrictBackfillTest` drives the command, taking the `DB::getDriverName()` MySQL `JOIN … UPDATE` branch.

**But MariaDB is not MySQL 8 — do not use MariaDB as the CI service.** The divergence that matters:

- All **11** `$table->json()` columns are stored as **`longtext`** on MariaDB but become the **native `json` type** on MySQL 8 (verified via `information_schema`). MySQL 8 *normalizes* JSON on write: keys reordered, whitespace stripped, duplicate keys dropped.
- The risk is contained, not absent: every JSON column is cast to `'array'` in its model, and every test asserts on **decoded arrays** (`AuditLogTest:49`, `BarangayCoverageTest:282`, `SiteDedupeTest:87`) rather than raw strings — so key reordering is invisible to them. Nothing asserts an exact JSON string round-trip.
- Still unexercised by any local run: MySQL 8's optimizer/query plans, native-JSON indexability (needs generated columns), and collation defaults.

- [ ] Add a **`mysql:8`** service to `ci.yml` (matrix: sqlite + mysql, keep both). **Not `mariadb`** — it would pass while hiding the JSON-type difference.
- [ ] Pin `sql_mode` explicitly (`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES`) on the service so CI matches MySQL 8 defaults instead of trusting the image.
- [ ] Add one JSON round-trip test asserting a **normalized** document survives — the gap no local run can see.
- [ ] Confirm the production MySQL version and its `sql_mode`; if it is MySQL 8, `ONLY_FULL_GROUP_BY` is on and the third row above is representative.
- **Acceptance:** suite green against **MySQL 8** in CI — not SQLite, not MariaDB.
- *Harness (untracked):* `phpunit.mysql.xml` points the suite at a MySQL-protocol server. Recreate the scratch DB with `CREATE DATABASE pred_tracking_mysql_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` — never point it at a dev database (`RefreshDatabase` drops everything).

### F2 — Close the exploitable gaps

✅ **Closed 2026-09-13 (verified in tree — do not redo):**

- Heartbeat race — `lockForUpdate()` inside the existing transaction in `Api/HeartbeatController`; double-post keeps one row (`RequestHardeningTest`).
- `Api/SiteApiController` — `per_page` validated and capped at 200; `show()` history capped at latest 90. The identical hole in `Api/DailyStatusApiController` was capped too (`RequestHardeningTest`, `ApiCoverageTest`).
- `UserController` — `$request->boolean('status')`, so `?status=false` is no longer truthy (`RequestHardeningTest`).
- `.env.production.example` — `SESSION_DRIVER=file`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=file`, `CACHE_PREFIX=freewifi_monitor`.

Still open:

- [ ] **CSV formula injection.** `ReportController::downloadCsv` (`:248–250`) `fputcsv`es raw DB values with no guard. A site name, barangay or remark beginning with `=`, `+`, `-` or `@` is executed as a formula when a DICT staffer opens the CSV in Excel. Neutralise those cells (prefix `'`) — it affects every CSV export.
- [ ] **Missing security headers.** `SecurityHeaders` sets nosniff, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy` — but no **Content-Security-Policy** and no **HSTS**. CSP needs care: Inertia inlines the page payload and Ziggy emits an inline `<script>`, so start permissive (`script-src 'self' 'unsafe-inline'`) or go nonce-based — a strict policy will white-screen the app. HSTS is safe to add once the TLS cert is confirmed.

### F3 — Make failures visible before users report them

✅ **Closed 2026-09-13:** `deploy/fpiap-worker.conf` (Supervisor unit) committed, and `queue:check` reports a stuck-PENDING backlog (exit 1, for cron/monitors) — covered by `QueueCheckTest` and wired into `docs/DEPLOY.md` §3.

Still open:

- [ ] **Sentry.** Installed and wired (`sentry/sentry-laravel ^4.27`, `Sentry\Laravel\Integration` in `bootstrap/app.php`, providers auto-discovered) but `.env.production.example:70–71` still has `SENTRY_ENABLED=false` and an empty DSN — production errors currently go nowhere. Set the DSN (**config only, no code change**) and **verify one real event lands**.
- [ ] **External monitor on `/up`.** The endpoint exists; nothing pings it, so PHP-FPM or nginx death is silent.
- [ ] Confirm log rotation and retention on the server (Laravel's daily channel vs the host's logrotate — pick one and write it down).

### F4 — Prove recovery, not just backup

- [ ] **Restore drill.** `docs/DEPLOY.md` §5.6 already documents the quarterly steps — but no restore has ever actually been run. Execute it once: restore last night's dump into a scratch database, boot the app against it, time it, and record the result (and any step that turned out to be wrong) back into §5.6. A backup that has never been restored is a hypothesis.
- [ ] **Queue failure drill.** Kill the worker mid-import; verify the batch is marked `FAILED` and audited, and that retry works.
- [ ] **Degradation check.** With Telegram and Sentry unreachable, confirm requests neither hang nor 500 (alert delivery is in the request path for some flows).

### F5 — Test depth

✅ **Closed 2026-09-13 (verified in tree — do not redo):**

- `PageSmokeTest` — HTTP render smoke over `/`, `/daily-ops`, `/map`, `/reports`, `/wallboard` (asserts 200 + component). This is the layer that would have caught both shipped bugs (blank Projects page, Sites 500).
- `ReportContentTest` — renders the incidents/progress/fleet Blades with seeded data and asserts key figures **and** narrative bullets land on the page, so a template producing wrong numbers now fails.
- `ApiCoverageTest` — `probe-tokens.destroy` (own revoke works, another user's id → 404) and `/api/daily-statuses` + `/api/daily-statuses/site/{site}` (filters, scoping, per_page cap).

Still open:

- [ ] **Playwright smoke** — optional. The HTTP layer is already covered by `PageSmokeTest`; Playwright would add client-side console-error and hydration coverage, at the cost of browser binaries + a JS-capable CI runner.
- [ ] **PHPStan level 6** (207 errors; incremental). Null-safety matters most in the reporting/coverage services, where wrong numbers originate.

### F6 — Before handover

- [x] **Operator manual** — done 2026-09-13: `docs/USER_GUIDE.md` (69 lines) covers the Daily Ops lifecycle (`DRAFT → SUBMITTED → APPROVED`, locked rows final, 07:00 reminder / 23:00 snapshot), the map filters and legend buckets, and both report paths. Ops-side stays in `docs/DEPLOY.md`.
- [ ] **Incident runbook.** `docs/DEPLOY.md` is a *deploy* runbook — there is no "an alert just fired, now what" page: what each rule means, first diagnostic, who to call, what to do when the queue is stuck. Needed before the NOC actually relies on `alerts:evaluate`.

### F7 — Data lifecycle

- [x] **417 soft-deleted duplicate sites — decided: retain** (2026-09-13). `HeartbeatController` resolves stale AP codes through the trashed rows' `metadata.merged_into`, so deleting them would turn old AP codes into 404s. Storage is negligible; revisit at 10× growth.
- [ ] **Confirm the `audit:prune` 90-day retention satisfies the audit requirement.** The prune exists and is scheduled monthly; what is missing is an owner statement that 90 days *is* the required window — otherwise the trail may be deleted before an audit needs it.

---

## Open (backlog)

1. ⏸️ **ON HOLD — do not start.** *Ship local tree to production* (`fpiapr2.dictr2.cloud`). Owner decision (2026-09-14): fortify the app first — see "Fortify before deploy" above. Kept here so the runbook survives.
   - When it resumes: migrate (`2026_09_08_*`), `deploy.sh` runs `sites:backfill-regions` itself, Vite to **both** web root `build/` and `fpiap-app/public/build` (`public/build` is gitignored). Preserve `.env`. `route:cache` is OK. Split CloudPanel layout: do not run `deploy.sh` as-is without copying `public/build` to the domain folder.
   - ⚠️ Do **not** rely on the migration to fill `sites.region`: it runs before the workbook import, so it matches zero rows (measured locally — coverage stayed at 14.3%). The `sites:backfill-regions` step in `deploy.sh` is what does the work. If you deploy by hand, run it yourself and confirm it reports 100%.
   - ⚠️ Prerequisite before cut-over: **F1 (MySQL CI)** must be done. The suite now *has* been run against a MySQL-protocol engine and passes (see F1), so this is no longer a total unknown — but it was **MariaDB, not MySQL 8**, and CI still runs only SQLite. Pin `mysql:8` before shipping.
2. **Analytics PDF reports** — phases 1–4 done (shared kit, the four enriched PDFs, `ops_period`/`fleet`/`incidents`/`progress` packs, builder UI + combined pack + CSV). **Only phase 5 is open** and it is blocked on DICT input, not on engineering: SLA target, scheduled monthly pack, solar/GB sections. See "Analytics PDF reports (open)" below.
3. **Live NMS polling** — bind a real SNMP/REST `NmsClient` and schedule `nms:pull` (needs a reachable NMS/gateway). Reports keep using `site_daily_statuses` until then.
4. **SMS** — if Telegram is not enough (ClickSend/Twilio), beside `App\Services\Telegram`.
5. **Next by importance (unblocked, scheduled 2026-09-13)**
   - [x] **Firmware fleet UI** (done 2026-09-13) — `firmware=outdated` filter + counter on Devices (`DeviceFirmwareFilterTest`), same null-semantics as the fleet pack (counter hidden when `APPROVED_FIRMWARE` unset); filter combos now preserved across toggles.
   - Later (larger or thinner value): solar power analytics (sparse `solar_w` — wait for probe data). SLA-vs-target PDF is phase 5 of reports, after DICT sets a target.
   - Needs owner input before building (no spec exists — building blind means rework):
     - **Field inspection form** — proposed: `site_inspections` (site, inspector, visited_at, condition good/degraded/critical, findings text, optional auto-ticket on critical). Confirm fields + who may file (encoders? managers?) and whether photos are in scope (storage + moderation cost).
     - **Public unauthenticated map** — publishing exact coordinates of government infra is a security call. Proposed: barangay-aggregated bubbles only (counts + dominant health, no markers/popups/site names), cached, rate-limited. Confirm aggregation level + whether DOWN sites may show publicly.
6. **Map marker glow-up** (done 2026-09-13, spec was `Plan_ui.md` Slices 7–9) — status cluster bubbles with counts + dominant % (custom `iconCreateFunction`, halo CSS, no new deps), CARTO light basemap, live legend chips (UP / DOWN / NO_NMS / NO_DATA), merge/detail slider.
7. **`Plan_revision` §2 leftovers — all four closed 2026-09-13** (re-verified in tree 2026-09-14; this entry is kept only as a record). Small, self-contained, good first tickets — already taken:
    - [x] `UserController.php:24` — `(bool) $request->input('status')` made `?status=false` truthy. Now `$request->boolean('status')` (`RequestHardeningTest`).
    - [x] `Api/SiteApiController` — `per_page` validated/capped at 200; `show()` history capped at latest 90. Same cap extended to `Api/DailyStatusApiController` (identical hole next door) — covered in `RequestHardeningTest` + `ApiCoverageTest`.
    - [x] `Api/HeartbeatController` — concurrent first-beats serialized with `lockForUpdate()` inside the existing transaction (duplicate-key 500 gone); double-post keeps one row (`RequestHardeningTest`).
    - [x] `.env.production.example` — `CACHE_PREFIX=freewifi_monitor`, sessions + cache to `file` (single VPS); queue stays on `database` for worker visibility.
8. ~~**Two endpoints with no test coverage**~~ — **done 2026-09-13** (`ApiCoverageTest`): `probe-tokens.destroy` (own revoke works, another user's id → 404) and `/api/daily-statuses` + `/api/daily-statuses/site/{site}` (filters, scoping, per_page cap).

### Operational readiness — verified gaps (2026-09-14 sweep)

Found by sweeping for what is *absent*, not what is broken. None of these is in another plan. Ordered by value/effort.

| # | Gap | Evidence | Fix |
|---|---|---|---|
| 1 | **Error monitoring is installed but inert** | `sentry/sentry-laravel ^4.27` in `composer.json`, `Sentry\Laravel\Integration` wired in `bootstrap/app.php`, providers auto-discovered — but `SENTRY_ENABLED=false` and `SENTRY_LARAVEL_DSN=` empty in both env examples. Production errors currently go nowhere | set the DSN (no code change) |
| 2 | **No committed queue-worker supervision** | Scheduler has 14 entries, but no Supervisor/systemd unit in the repo. A dead worker leaves reports/imports `PENDING` forever — the amber stale-queue banner is the only signal | done 2026-09-13: `deploy/fpiap-worker.conf` Supervisor unit + `queue:check` liveness (stuck-PENDING → exit 1, for cron/monitors; `QueueCheckTest`), wired in `docs/DEPLOY.md` §3 |
| 3 | **No external uptime monitoring** | `/up` health endpoint exists, but nothing pings it. PHP-FPM or nginx death is silent | one external monitor |
| 4 | **Backup restore has never been rehearsed** | Nightly to two encrypted destinations + `backup:monitor` at 08:00, but no restore has ever been run | quarterly drill (`docs/DEPLOY.md` §5.6) |
| 5 | **No browser/E2E test** | Only PHPUnit + Vitest unit. The two shipped "blank page" and "500" bugs were invisible to both — a render smoke test is the missing layer | cheap layer done 2026-09-13 (`PageSmokeTest`: `/`, `/daily-ops`, `/map`, `/reports`, `/wallboard` assert 200 + component). Full Playwright remains optional — needs browser binaries + a JS-capable CI runner |
| 6 | **PDF content is not asserted** | `ReportExportTest` checks the `%PDF` magic bytes and `DONE` status, never the numbers — the `$bullets` undefined-variable outage proved template bugs are real | done 2026-09-13 (`ReportContentTest` renders the incidents/progress/fleet Blades to HTML with seeded data and asserts key figures + bullets land on the page) |
| 7 | **PHPStan level 6** | Level 5 is clean; level 6 was 290 errors | incremental slice done 2026-09-13: all 95 missing return/param types across Http/Console/Models/Policies/Observers/Mail/Jobs (native types, suite still 231 green). Remaining ~199 are docblock generics + Services array-shapes — a separate shaped-data pass, still deferred |
| 8 | **No operator manual** | `docs/DEPLOY.md` is ops-only. Encoders/managers using Daily Ops, approvals and reports have no guide | done 2026-09-13: `docs/USER_GUIDE.md` (Daily Ops lifecycle, map, reports builder + scheduled pack, alerts/tickets, accounts/probe tokens) |
| 9 | **417 soft-deleted duplicate sites** | `sites:dedupe` keeps them deliberately, but no retention policy is written down | decided 2026-09-13: **retain** — `HeartbeatController` resolves stale AP codes via the trashed rows' `metadata.merged_into`; deleting them turns old codes into 404s. Negligible storage; revisit at 10× growth |

---

## Analytics PDF reports (open)

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
- [ ] SLA vs target (`SLA_UPTIME_TARGET`, pass/fail column) — confirm target and whether `NO_NMS` stays in the denominator
- [x] `reports:scheduled` monthly provincial pack + mail/Telegram recipients (done 2026-09-13 — previous-month province packs, skip-guard + `--force`, `REPORT_SCHEDULED_EMAIL` → `WATCHDOG_EMAIL` fallback, Telegram when configured, `ScheduledReportsTest`)
- [ ] Solar / GB-delivered sections only once probe data is populated

Build order: analytics + kit → enrich the four PDFs → `ops_period` + `fleet` → builder UI → incidents/progress → SLA/schedule.

---

## Execution plan — finish everything (2026-09-11)

Skills: Ponytail governs — reuse `ReportAnalytics`/partials/`GenerateScopedReportRequest`, one runnable check per pack, gates green before each commit.

### R1 — incidents + progress packs [done — was stale-marked open; verified 2026-09-13 in tree: `reports.incidents`/`reports.progress` routes, `ReportController@incidentsPdf|progressPdf`, `ReportingService::incidentsData|progressData`, combined `sections`]
- `incidents`: alert severity breakdown, ticket backlog, MTTA/MTTR from `triggered_at`/`acknowledged_at`/`resolved_at` (+ tickets `created_at`/`resolved_at`); open alert + ticket lists. Route/job/view/tests, same pattern as `ops_period`/`fleet`.
- `progress`: weighted accomplishment % (`weight_pct` × `pct_complete`), per-milestone bars, overdue list (`target_date` past + incomplete). Route/job/view/tests.

### R2 — builder UI + combined PDF [done]
- Reports page: period presets (7/14/30d) + shared `GeoFilterFields` + section checkboxes + one Generate (per-pack cards kept alongside); scope line + pagination on export history.

### R3 — Laravel 11 → 12 upgrade [done 2026-09-11]
- Framework now 12.69.2 (`laravel/framework: ^12.0`, companions resolved, `composer audit` clean — CVE-2026-48019 + signed-URL advisory gone); Pint normalizations from the new fixer version; CI `composer audit` is blocking again.

### R4 — cutover readiness [done 2026-09-11, cutover itself owner-side]
- `deploy.sh` reviewed: maintenance window + trap rollback, pre-migration dump, caches (`config`/`event`/`route`/`view` all verified), scoped permissions, `queue:restart`; `sites:backfill-regions` (idempotent) runs post-migrate so the region filter works from first boot.
- Frontend rebuilt on the final tree; dev-server smoke 200/200 on Laravel 12. Handoff = `docs/DEPLOY.md` §5 + tag the release before `--pull` deploy.

Blocked on owner input (not scheduled): live NMS bind, SMS provider, SLA target, TOTP hard-require rollout, backup restore rehearsal.

---

## Deploy notes (when shipping #1)

- Copy app + `storage/app/geo` (not into the nginx document root as PHP).
- Sync Vite `public/build` to the domain folder **and** `fpiap-app/public` (not in git).
- `php artisan route:cache` is allowed.
- Runbook: `docs/DEPLOY.md`. `deploy.sh` assumes a standard `public/` docroot — extra copy step required on this CloudPanel split layout.
