# Plan — DICT FreeWiFi Monitor

Living roadmap. **Done** = in the local repo. **Open** = not built, or built locally but not on production.

**Production (`fpiapr2.dictr2.cloud`):** still an older tree. GitHub `main` is ahead (`2829662` and later). Shipping the local tree is backlog #1. `route:cache` is now safe (single `dashboard` name; `/dashboard` 301s to `/`).

Companion docs: `Plan_revision.md` (2026-09-08 hardening log) · `Plan_ui.md` (visual roadmap).

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
- [x] Project PDF: exec KPIs + site register (type, daily status, devices, CIR) + DOWN episodes/tickets; province PDF: municipality rollup + scope cover + daily status; site-type PDF: coverage bars + uncapped appendix + `site_type`/`status` form fields; barangay PDF: scope-aware totals + uncovered annex per municipality
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

Out of scope (not started, not promised this slice): nationwide shapefiles, live GPS/NMS coordinates, changing Site Type codes, replacing Leaflet.

---

## Open (backlog)

1. **Ship local tree to production** (`fpiapr2.dictr2.cloud`) — migrate (`2026_09_08_*`), `deploy.sh` now runs `sites:backfill-regions` itself, Vite to **both** web root `build/` and `fpiap-app/public/build` (`public/build` is gitignored). Preserve `.env`. `route:cache` is OK. Split CloudPanel layout: do not run `deploy.sh` as-is without copying `public/build` to the domain folder.
2. **Analytics PDF reports** (exec summary + ops annexes) — phases 1–2 done (kit + enriched four); phases 3–5 open below. Uses data already in the DB. No live NMS required for phases 1–4.
3. **Live NMS polling** — bind a real SNMP/REST `NmsClient` and schedule `nms:pull` (needs a reachable NMS/gateway). Reports keep using `site_daily_statuses` until then.
4. **SMS** — if Telegram is not enough (ClickSend/Twilio), beside `App\Services\Telegram`.
5. **Later ops** (docs): firmware fleet *UI*, solar power analytics (sparse `solar_w`), field inspection form, public unauthenticated map. SLA-vs-target PDF is phase 5 of reports, after DICT sets a target.

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
- [ ] `reports:scheduled` monthly provincial pack + mail/Telegram recipients
- [ ] Solar / GB-delivered sections only once probe data is populated

Build order: analytics + kit → enrich the four PDFs → `ops_period` + `fleet` → builder UI → incidents/progress → SLA/schedule.

---

## Execution plan — finish everything (2026-09-11)

Skills: Ponytail governs — reuse `ReportAnalytics`/partials/`GenerateScopedReportRequest`, one runnable check per pack, gates green before each commit.

### R1 — incidents + progress packs [open]
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
