# Plan — DICT FreeWiFi Monitor

Living roadmap. **Done** = in the local repo (verified 2026-09-01). **Open** = not built, or built locally but not on production.

**Production (`fpiapr2.dictr2.cloud`):** still the older deploy. Missing 2FA, map geo/coverage, geo files, Phase 2 alert engine. Shipping the local tree is backlog #1.

---

## Done

### Hardening (production baseline)
- [x] Scoped RBAC (`can:` + policies), transactional writes, MySQL-safe SQL
- [x] Audit log redaction + payload caps, throttled auth routes, random `setup.ps1` admin password
- [x] Queued Excel imports (atomic per row) and queued PDF reports with tracked exports
- [x] CI: GitHub Actions, PHPStan L4, Pint, ESLint, PHPUnit — **122 tests**

### Data platform
- [x] Region II workbook importer (`php scripts/import-region-workbook.php`)
- [x] Schema aligned with workbook (classification, providers, lifecycle, NO_NMS / DOWN_SERVER)
- [x] Local data loaded: 1,132 sites · 253 AP devices · 14,270 day records
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

### Alerts console (2026-09)
- [x] `/alerts`: active/resolved lists with severity filter, acknowledge + resolve actions (`daily.approve`), live counters
- [x] Rules CRUD on the same page (`users.manage`)

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
- [x] LGU holes closed: Cagaban/Cauayan aliases, Uyugan from OCHA barangays (95 LGUs)
- [x] Site Type coverage panel + queued PDF (`/map/coverage`, `/reports/site-type`)
- [x] Tests: `LegislativeDistrictBackfillTest`, `MapGeoJsonTest`, `SiteCoverageTest`

### Barangay coverage (2026-09)
- [x] Installed vs total barangays (`BarangayCoverageService`, `/map/barangay-coverage`, `/reports/barangay-coverage`)
- [x] `barangay_references` + `barangays:sync-reference` (upsert-only)
- [x] **PSGC reconciliation: 2,311 barangays — exact PSA match** (`barangays:import-psgc`, 2026-07 publication; per province: Batanes 29 · Cagayan 820 · Isabela 1,055 · NV 275 · Quirino 132; every barangay stamped with its PSGC code)

Out of scope (not started, not promised this slice): nationwide shapefiles, live GPS/NMS coordinates, changing Site Type codes, replacing Leaflet.

---

## Open (backlog)

1. **Ship local tree to production** (`fpiapr2.dictr2.cloud`) — migrate, `LegislativeDistrictSeeder` + `AlertRuleSeeder`, `sites:backfill-districts`, `storage/app/geo`, Vite to **both** web root `build/` and `fpiap-app/public/build`, `cache:clear` / `view:clear`. Do not `route:cache`.
2. **Live NMS polling** — bind a real SNMP/REST `NmsClient` and schedule `nms:pull` (needs a reachable NMS/gateway).
3. **SMS** — if Telegram is not enough (ClickSend/Twilio), beside `App\Services\Telegram`.
4. **Phase 3 ops** (docs): SLA PDF vs target, firmware fleet view, solar power analytics, field inspection form, public unauthenticated map.

---

## Deploy notes (when shipping #1)

- Copy app + `storage/app/geo` (not into the nginx document root as PHP).
- Sync Vite `public/build` to the domain folder **and** `fpiap-app/public`.
- Duplicate route name `dashboard` — never `php artisan route:cache`.
- Runbook: `docs/DEPLOY.md`.
