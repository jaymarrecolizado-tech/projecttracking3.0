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
- Feature tests for Projects/Sites pages (suite now 65 tests)

## Next implementation — Map geo filters, area highlight, click-to-filter, Site Type actual vs registered

**Goal.** On Map View, filter **deployed devices** (not only sites) by Province → Congressional district → Municipality → Barangay. The selected area is highlighted on the map. Clicking an area applies that filter. Reports (on-map panel + PDF) show **actual (sites with a deployed device) vs registered site count**, broken down by **Site Type**.

Current data (local/production copy): 1,132 sites across 5 Region II provinces, 94 municipalities, 394 barangays; 253 active deployments; `sites.district` is empty; `site_type` is coded (`PES`, `PHS`, `LGU-BRGY`, `DRRMO`, …). Leaflet map today only filters by project + site status and plots site points — no polygons, no device layer, no cascade, no site-type report.

---

### 1. What “done” looks like

1. Cascading dropdowns: Province → Congressional district → Municipality → Barangay (plus existing Project / site status).
2. Map shows **deployed-device markers** for the current filter (clustered). Sites without a live deployment stay off this layer (optional toggle: “All sites”).
3. The matching admin polygon(s) **highlight** (fill + outline). Zoom/fit bounds to the selection.
4. Clicking a province / district / municipality / barangay polygon **sets that filter** (and clears children). Clicking empty map / Clear resets.
5. Stats panel + PDF report: per Site Type, **registered sites** vs **actual (has active `device_deployments`)** vs **deployed device count**, scoped to the same geo filter.

---

### 2. Data gaps to close first

| Gap | Fact | Approach |
|---|---|---|
| Congressional district | `sites.district` is 0/1132 | Seed a **municipality → legislative district** lookup for Region II (Comelec 19th Congress), write `sites.district`, keep lookup for future imports |
| Boundary polygons | None in the app | Store simplified GeoJSON by PSGC (or name+parent) under `storage/app/geo/` (not the public web root). Serve via authenticated JSON endpoints. Do not vendor 50MB raw NAMRIA files |
| Name matching | Site names vs shapefile names will drift (`Tuguegarao City` vs `Tuguegarao`) | Normalize (case, “City of”, “Sto.”) + alias table. Prefer PSGC codes once we can attach them |
| Site Type labels | Codes only (`PES`, `PHS`, …) | `config/site_types.php` map code → label (e.g. PES = Public Elementary School). Unknown codes still display as-is |

Do **not** invent district values in application code per request — persist them so Sites, Map, and Reports stay consistent.

---

### 3. Architecture (reuse existing pieces)

Stay on Laravel 11 + Inertia Vue 3 + Leaflet already loaded in `app.blade.php`. Extend, do not replace.

| Existing | Role in this work |
|---|---|
| `MapController` + `GET /map/geojson` | Add filter params: `district`, `barangay`, `site_type`, `deployed_only` |
| `GeoJsonService` | Point features for **devices** (join `device_deployments` where `removed_at` is null → `sites`). Keep site points behind toggle |
| `sites.province` / `municipality` / `barangay` / `district` / `site_type` | Filter columns (index `district` after backfill) |
| `Device::scopeDeployed` + `Site::activeDeployments` | “Actual” = site with at least one active deployment |
| `ReportingService` + queued `GenerateReport` | New export type `site_type` (same job `match`, new blade) |
| `Reports/Index.vue` | New card: Site Type coverage, geo + optional project |

Split `resources/js/Pages/Map/Index.vue` before it grows: `MapFilters.vue`, `MapStatsPanel.vue`, composable `useLeafletMap.js`. Keep each file under ~250 lines.

---

### 4. Backend

**4.1 District backfill**

- Table `legislative_districts`: `province`, `municipality`, `district` (e.g. `Cagayan` / `Aparri` / `1st District`). Unique on province+municipality.
- Seeder `LegislativeDistrictSeeder` for the five Region II provinces.
- Artisan `sites:backfill-districts` — `UPDATE sites SET district = …` from the lookup (idempotent). Run on deploy after migrate.
- Importer (`ImportService`) fills `district` from lookup when the workbook has no District column.

**4.2 Boundary service**

- `GeoBoundaryService`: given `level` + filter, return a GeoJSON FeatureCollection of polygons for that level, clipped to parent (e.g. municipalities of Cagayan only).
- Files: `storage/app/geo/provinces.geojson`, `municipalities.geojson`, `districts.geojson`, `barangays.geojson` (Region II subset only to keep size down). Properties must include `name`, `parent_name`, `psgc` when available, `level`.
- `GET /map/boundaries?level=province|district|municipality|barangay&province=&district=&municipality=` — auth same as map; cache with `Cache::remember` (file/db store already used).
- Highlight rule: when a filter is set, return **only the selected feature** (or children if drilling down) with `selected: true`. Unselected siblings can render as a faint outline so click-to-filter still works.

**4.3 Map GeoJSON (devices)**

Extend `GeoJsonService::getSitesForMap` (or add `getDeployedDevicesForMap`):

- Filters: existing `project_id`, `status`, `region`, `province`, `municipality`, `island_group` **plus** `district`, `barangay`, `site_type`.
- Default map mode `deployed_only=1`: features are devices with `status = deployed` and an active deployment; geometry = site lat/lng; properties include `asset_tag`, `serial_number`, `model`, `site_id`, `location_name`, geo fields, `site_type`.
- Skip rows with null coordinates.
- Marker color: daily status of the **site** (UP / DOWN / NO_NMS) so ops still see health.

**4.4 Cascade options API**

`GET /map/filter-options?province=&district=&municipality=` returns distinct values **from sites that have data**, not the full shapefile:

```json
{ "provinces": [], "districts": [], "municipalities": [], "barangays": [], "site_types": [] }
```

Children empty until parent is chosen. `MapController@index` can pass the unfiltered lists for first paint to avoid a waterfall.

**4.5 Coverage stats (actual vs registered by Site Type)**

New `SiteCoverageService` (keep `ReportingService` for PDFs only):

For the same geo/project filters:

| Site Type | Registered sites | Actual (with deployed device) | Gap | Devices | Coverage % |
|---|---|---|---|---|---|
| PES | 287 | … | … | … | … |
| … | | | | | |
| **Total** | 1132 | 253 | … | 253 | … |

- Registered = `COUNT(sites)` in filter (not soft-deleted).
- Actual = sites in filter that have `device_deployments.removed_at IS NULL`.
- Devices = count of those deployments (or distinct devices).
- `GET /map/coverage` returns this table + totals for the stats panel (JSON). Same query used by the PDF.

**4.6 PDF report**

- `POST /reports/site-type` with `province`, `district`, `municipality`, `barangay`, `project_id` (all optional = nationwide / all projects).
- `GenerateReport` `type = site_type`.
- Blade `resources/views/reports/site-type-coverage.blade.php`: filter summary, totals, table by site type, optional site list appendix if count ≤ 200.
- Extend `report_exports.type` string (already `varchar(30)`). Tests in `tests/Feature/ReportExportTest.php`.

---

### 5. Frontend (Map View)

**Filters (left-to-right, cascading)**

1. Project (existing)
2. Province
3. Congressional district (disabled until province set; hidden if that province has no districts in lookup)
4. Municipality (narrowed by province + district)
5. Barangay (narrowed by municipality)
6. Site type (optional extra constraint)
7. Toggle: Deployed devices (default on) / All sites
8. Clear filters

Changing a parent clears children and reloads points + boundaries + coverage.

**Highlight + click-to-filter**

- Leaflet `L.geoJSON` polygon layer under markers (`pane` below overlay).
- Selected feature: teal fill ~0.35 opacity, 2px stroke (`#0F1B2D` / FPIAP teal). Others: no fill, 1px muted stroke, pointer cursor.
- `click` on feature → set the corresponding filter and `fitBounds`.
- After a municipality is selected, swap polygon layer to barangays of that LGU so the next click drills in.
- Device markers: `L.markerClusterGroup` (add Leaflet.markercluster via CDN next to existing Leaflet, or npm). Popup: asset tag, site name, site type, barangay/municipality, link to `sites.show` / `devices.show`.

**Stats panel** (card beside or below the map, not a modal)

- Totals: registered sites · actual · devices · coverage %.
- Compact table by Site Type for the current filter.
- Button “Generate PDF” → existing reports queue (or inline POST to `reports.site-type` with current filters).

**URL state** so map shares work: query string `?province=Cagayan&municipality=Aparri` via `router.get` only for the Inertia page; geojson/coverage remain `fetch` to `/map/geojson` and `/map/coverage`.

---

### 6. Reports page

Add a third card **Site Type coverage (actual vs registered)** on `Reports/Index.vue`: same cascade dropdowns as the map (reuse a small `GeoFilterFields.vue`), submit to `reports.site-type`. Polling/download unchanged.

---

### 7. Implementation order

| Step | Work | Notes |
|---|---|---|
| A | `LegislativeDistrictSeeder` + `sites:backfill-districts` + index on `sites.district` | Unblocks district filter; 0 rows today |
| B | `config/site_types.php` labels | Used by map panel and PDF |
| C | Region II GeoJSON in `storage/app/geo` + `GeoBoundaryService` + `/map/boundaries` | Start with province + municipality; districts next; barangays last (largest) |
| D | Extend `GeoJsonService` + map filters + device markers + cluster | Feature-visible without polygons |
| E | Polygon highlight + click-to-filter + cascade UX | Depends on C |
| F | `SiteCoverageService` + `/map/coverage` panel | Same filters as D |
| G | Queued Site Type PDF + Reports card | Same service as F |
| H | Tests + `npm run build` | See §8 |

Ship A→D first if boundaries lag (markers + filters still useful). Do not block device filtering on perfect barangay polygons.

---

### 8. Tests (PHPUnit, no mocked prod data)

- `LegislativeDistrictBackfillTest` — municipality maps to expected district; unknown LGU left null.
- `MapGeoJsonTest` — deployed-only omits sites without deployments; province/district/municipality/barangay/site_type constrain features; properties include `asset_tag`.
- `MapBoundariesTest` — province level returns Region II features; municipality level with `province=Cagayan` does not include Isabela.
- `SiteCoverageServiceTest` — registered vs actual vs devices by `site_type` for a fixture province.
- `ReportExportTest` — `reports.site-type` queues `DONE` PDF; unauthorized 403.
- Feature: `map.index` 200 for a viewer role.

---

### 9. Permissions, performance, deploy

- Map + coverage + boundaries: `sites.view` (same as today’s `/map`). PDF: `reports.view` to request, `reports.export` / owner to download (match existing).
- 1,132 points is fine; clustering is for UX. Boundaries: Region II subset + HTTP cache. Do not load all PH barangays at once.
- After code lands: migrate, seed districts, `sites:backfill-districts`, copy `storage/app/geo`, `php artisan cache:clear`. Do **not** `route:cache` (duplicate `dashboard` name). Rebuild Vite and sync `public/build` to the web root **and** `fpiap-app/public/build`.

---

### 10. Out of scope (this slice)

- Live GPS / NMS device coordinates (still site lat/lng).
- Nationwide shapefiles beyond Region II until the next region is imported.
- Changing Site Type codes in the workbook.
- Replacing Leaflet with Mapbox/Google.

---

## 🔲 Backlog (priority order)
1. **Map geo filters + highlight + Site Type coverage** — section above
2. **Deploy to Hostinger** — first real MySQL run: `mysqldump` on PATH for backups, `queue:restart` after deploy, cron `schedule:run`
3. **SMS/Telegram alerts** — needs a gateway choice (ClickSend/Twilio/Telegram bot); hook into `alerts:down`
4. **`device_metrics` time-series** — heartbeat currently stores one row/day; add high-frequency table + charts (docs Phase 2)
5. **DataTable shared component** — extract from Sites/Devices/DailyGrid tables
6. **Accessibility** — remaining: focus trap in mobile drawer, aria-live on ops counter, table captions
7. **2FA for admin accounts** — Laravel Fortify or TOTP package
