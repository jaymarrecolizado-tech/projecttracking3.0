# Plan — DICT FreeWiFi Monitor

Living roadmap. Completed work stays listed for context; backlog at the bottom.

## ✅ Shipped

### Hardening (production baseline)
- Scoped RBAC routes (create/edit/delete via policy methods), transactional writes, MySQL-safe SQL
- Audit log redaction + payload caps, throttled auth routes, random dev credentials
- Queued Excel imports (atomic per row) and queued PDF reports with tracked exports
- Test suite (55 tests), CI (GitHub Actions), PHPStan L4, Pint, ESLint

### Data platform
- Region II workbook importer: multi-sheet classification (rosters → sites + MAC-addressed AP devices; month sheets → daily triplets), lifecycle mapping, project auto-creation, idempotent re-imports (`php scripts/import-region-workbook.php`)
- Schema aligned with real data: site classification/providers/source-of-BW/lifecycle/acceptance/AP brand/declaration dates; NO_NMS + DOWN_SERVER statuses
- 1,132 real sites · 253 AP devices · 14,270 day records loaded

### Daily status operations
- **Daily Ops Board** (`/daily-ops`): date-scoped bulk entry, UP/DOWN/NO NMS toggles, DRAFT→SUBMITTED→APPROVED workflow, per-entry project-scoped permissions, LOCKED enforcement
- **Heartbeat API** (`POST /api/heartbeat`, Sanctum tokens via Profile → Field Probe Tokens); rejects LOCKED records with 409
- `statuses:remind` (07:00) encoder emails · `statuses:snapshot` (23:00) auto-NO_DATA
- `alerts:down` (15 min) DOWN-episode email alerts

### Visibility & ops
- Dashboard trends + uptime %, NOC wallboard (auto-refresh), map with filters, Sites search/filters incl. "Down today"
- Maintenance tickets, probe token management, DOWN alerts, warranty digest
- DB backups (spatie/laravel-backup, 02:15 daily), report/import cleanup jobs
- `nms:pull` command + `NmsClient` contract — bind an SNMP/REST client to go live

### User administration
- Create/edit users with role assignment and project scoping, deactivation enforced at login (`user:make` command)

### Branding & UX polish
- FPIAP rebrand across login, sidebar, wallboard, device labels, PDF report footers, mail subjects, APP_NAME
- Sites/Projects row-click navigation; Projects "New Project" button removed (projects are import/command-managed)
- Fixed silent camelCase/snake_case payload mismatches on Sites pages (`latest_daily_status`, `active_deployments`) with null-safe rendering
- Relative route URLs via Ziggy (`config/ziggy.php`) + `ASSET_URL` support for deploy behind a CDN/tunnel
- Feature tests for Projects/Sites pages

### Security & hardening (2026-09)
- **2FA (TOTP)** for privileged accounts: RFC 6238 engine (`App\Support\Totp`), QR enrollment on Profile (`users.manage` gated), login challenge screen (`/two-factor-challenge`, throttled 5/min), disable requires a current code; secret hidden from all serialization. Suite: 80 tests.
- **Telegram DOWN alerts** alongside email — `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`, free channel, no gateway contract; email stays the backbone. Fixed a silent dedupe bug (`sites.last_alerted_at` was not fillable → alerts re-sent every 15 min per episode).
- **device_metrics time-series** (docs §Phase 2): heartbeat accepts the full telemetry payload (uptime/cpu/mem/latency/clients/throughput/power/firmware), writes one row per beat; 48h sparklines on the device page; `metrics:prune` retention command (03:00 daily).
- **Deploy prep**: `docs/DEPLOY.md` runbook, `deploy.sh` mysqldump preflight, `ASSET_URL` documented.
- **DataTable** shared component (Sites/Devices/DailyGrid) and **accessibility** pass: mobile drawer focus trap + Escape + focus restore, aria-live ops counter, sr-only table captions.

### Map geo filters + Site Type coverage (2026-09)

Verified 2026-09-01: **implemented in the local repo; not on production** (`fpiapr2.dictr2.cloud` still has the old map — no `SiteCoverageService`, no `storage/app/geo`).

#### Implemented (local)

| Item | Where |
|---|---|
| `legislative_districts` + `sites:backfill-districts` | 1,132/1,132 local sites have a district; unknown LGUs stay NULL |
| `config/site_types.php` labels | PES, PHS, LGU-BRGY, … |
| Cascading dropdowns: Province → District → Municipality → Barangay + Project + Site Type | `GeoFilterFields.vue` on Map and Reports; `/map/filter-options` |
| URL state for map filters | `?province=&district=&municipality=&barangay=` |
| Deployed-device markers (default), clustered, colored by site daily status; All-sites toggle | `/map/geojson?deployed_only=1`, `useLeafletMap.js` |
| Province + municipality polygons, highlight, fit bounds, click-to-filter | `storage/app/geo/provinces.geojson`, `municipalities.geojson`, `/map/boundaries` |
| Site Type coverage panel (registered vs actual vs devices) | `SiteCoverageService`, `/map/coverage`, `MapStatsPanel.vue` |
| Site Type Coverage PDF | `POST /reports/site-type`, Reports page card |
| Tests | `LegislativeDistrictBackfillTest`, `MapGeoJsonTest`, `SiteCoverageTest` |

#### Remaining (local code complete — as of 2026-09-01)

| Item | Status |
|---|---|
| **Deploy this slice to production** | Still open — needs the server: migrate, `LegislativeDistrictSeeder` + `AlertRuleSeeder`, `sites:backfill-districts`, `storage/app/geo` ships with the repo, Vite build to **both** web root `build/` and `fpiap-app/public/build`. Do not `route:cache`. |
| **District polygons** | DONE — `districts.geojson`, 12 districts dissolved from municipality polygons via the `legislative_districts` lookup. |
| **Barangay polygons** | DONE — `barangays.geojson`, 2,197 barangays (OCHA COD-AB ADM4 subset, simplified). |
| **Click-to-filter for district / barangay** | DONE — full drill chain: province > district > municipality > barangay, click applies the matching filter. |
| **3 LGUs without polygons** | DONE — `Cagaban`/`Cauayan` alias polygons; `Uyugan` dissolved from its OCHA barangays. All 95 LGUs now highlight. |
| **Site status dropdown on the map** | DONE — restored on the map controls beside the deployed-devices toggle. |
| **Name/PSGC matching** | Open — municipality/barangay features now carry `psgc` codes; attach them to sites at the next import (`loc_id`/`prov_id` free). |

Out of scope until a later region import: nationwide shapefiles, live GPS/NMS coordinates, changing Site Type codes, replacing Leaflet.

---

### Barangay coverage report (2026-09-01)

- **Barangay Coverage — Installed vs Total**: barangays with at least one registered Free WiFi site vs the total barangays per municipality/province, with % remaining to install. `BarangayCoverageService` + `/map/barangay-coverage` + queued **PDF** (`/reports/barangay-coverage`, card on Reports page). Live on real data: **402 of 2,262 barangays covered (17.8%), 1,860 remaining**; Tuguegarao City 12/49 (24.5%).
- `barangay_references` reference table + `barangays:sync-reference` — totals are correctable to the PSA figure (2,311): the boundary layer matches PSA exactly for the big LGUs (Tuguegarao 49, Ilagan 91, Cauayan 65, Santiago 37, Batanes 29) but the OCHA snapshot leaves Region II **49 barangays short** overall; add missing rows to the table and percentages update immediately (upsert-only — manual rows survive re-syncs).
- Normalizer (`App\Support\NameNormalizer`) unifies "Ilagan City" / "City of Ilagan" / "Basco (Capital)" spellings between sites, PSA and boundary data.

## ✅ Shipped (2026-09-01, this slice)

- **Boundary layers complete**: `districts.geojson` (12 dissolved districts), `barangays.geojson` (2,197, OCHA COD-AB ADM4 subset), and all 95 LGUs with polygons — `Cagaban`/`Cauayan` aliases, `Uyugan` dissolved from its barangays. Full province > district > municipality > barangay click-to-filter; site-status control back on the map.
- **Phase 2 monitoring** (docs §4.2-4.3): `site_status_events` (one open episode per site; heartbeat closes `heartbeat_lost` episodes), `alert_rules` + `alerts` with the `alerts:evaluate` engine (every 5 min) — seeded defaults: offline >10 min, latency >150 ms for 30 min, battery <11.8 V, bandwidth >85% of CIR; notify via rule-role email + Telegram (warning/critical); auto-resolve on recovery. `device_metric_hourlies` + `metrics:aggregate` (hourly). Suite: 106 tests.

## 🔲 Backlog (priority order)
1. **Ship everything to production** (`fpiapr2.dictr2.cloud`) — migrate, seeders, `sites:backfill-districts`, Vite to both web roots, `cache:clear`; runbook `docs/DEPLOY.md`
2. **Alerts UI** — list/acknowledge alerts and manage rules (tables + engine done; `acknowledged_at/by`, `escalation_level` columns ready)
3. **PSA barangay reconciliation** — source the official PSA 2,311 list and add the ~49 missing barangays to `barangay_references` (upsert-only; percentages update immediately)
4. **PSGC join for sites** — attach `adm*_pcode` to sites at the next import so name matching goes away
5. **Controller polling** — bind a real SNMP/REST client to `nms:pull` when sites are NOC-reachable
6. **SMS gateway** — if Telegram alone is insufficient (ClickSend/Twilio) — hook next to the Telegram service
