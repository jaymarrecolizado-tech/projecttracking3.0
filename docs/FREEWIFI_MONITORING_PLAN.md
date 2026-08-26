# Free WiFi Program Monitoring Platform — Improvement Plan

> Repurposing **projecttracking3.0 (DICT-MRIS v1.1)** into a dedicated monitoring platform for the
> **Free WiFi for All / Broadband ng Masa** program — focused on deployed-device tracking with full
> specs, live health monitoring, and operations reporting.
>
> Prepared: 2026-08-25 · Stack: Laravel 11 · Vue 3 · Inertia 2 · Tailwind 3 · SQLite/MySQL

---

## 1. Current State Analysis

The app already has a solid Free WiFi foundation that we keep and build on:

| Existing | What it does | Verdict |
|---|---|---|
| `Project` → `Site` hierarchy | AP sites with nationwide ID, AP site code, geo-coords, province/municipality/barangay, ISP provider, last-mile tech, bandwidth CIR | ✅ Keep — core of the program registry |
| `SiteDailyStatus` | Per-day UP/DOWN/NO_DATA, unique users, bandwidth utilization Mbps, uptime %, DRAFT→SUBMITTED→APPROVED→LOCKED approval workflow | ✅ Keep — becomes the rollup target for automated telemetry |
| `FreewifiImportBatch` + Excel import | Bulk site/status ingestion with error logs and queue processing | ✅ Keep — extend to import device inventories |
| Leaflet map (`GeoJsonService`) | Sites on map by project/status/region | ✅ Keep — upgrade to live status colors |
| Reports (DomPDF) | Project & province PDF reports | ✅ Keep — add device & SLA sections |
| Roles/Permissions + AuditLog + Policies | RBAC already wired through `$this->authorize()` | ✅ Keep — extend with ops roles |
| Milestones, Accomplishments | Deployment progress tracking | ⚠️ Simplify — fold into "Deployment" module |

**What's missing (the gap):** there is **no `Device` concept at all**. Monitoring is manual daily
entry or CSV import at the *site* level. The app cannot answer: *what hardware is deployed where,
what shape is each unit in right now, and who touches what when.*

---

## 2. Target Vision

One platform that answers, in order of operational priority:

1. **Is every Free WiFi site online right now?** (NOC wallboard view)
2. **What equipment is installed at every site** — full specs, serials, firmware?
3. **When something breaks, what broke, since when, and who was notified?**
4. **How is the program performing** — uptime SLA, users served, bandwidth delivered, per district?

---

## 3. Phase 1 — Device Registry (Foundation)

New domain: **device catalog → devices → deployments**.

### 3.1 New tables

```
device_models (catalog of spec sheets)
├─ id, manufacturer, model_name, model_number
├─ type            enum: outdoor_ap, router, switch, cpe, solar_panel, charge_controller,
│                        battery, ups, poe_injector, antenna, camera, other
├─ wifi_standard   enum: wifi4, wifi5, wifi6, wifi6e, wifi7, none
├─ specs           JSON: { radio_bands: "2.4/5GHz", max_throughput_mbps, max_clients,
│                          poe_standard, power_draw_w, ip_rating, temp_range_c,
│                          antenna_gain_dbi, ports, sim_slots, capacity_ah, wattage }
├─ datasheet_url, photo_path
└─ is_active

devices (each physical unit)
├─ id, device_model_id FK
├─ asset_tag          unique — printed QR label
├─ serial_number      unique
├─ mac_address        nullable, unique
├─ firmware_version   nullable
├─ status             enum: in_stock, deployed, under_repair, retired, lost
├─ condition          enum: new, good, degraded, faulty
├─ purchase_order_no, supplier, unit_cost, purchased_at, warranty_until
└─ notes, timestamps, soft_deletes

device_deployments (assignment history — many-to-many over time)
├─ id, device_id FK, site_id FK
├─ role_at_site     enum: primary_ap, backup_ap, backhaul, power, surveillance, other
├─ installed_at, removed_at nullable, installed_by FK users
└─ install_notes

maintenance_logs
├─ id, device_id nullable FK, site_id nullable FK
├─ type             enum: inspection, repair, firmware_upgrade, replacement, cleaning, reboot
├─ performed_by FK users, performed_at, downtime_minutes, cost, findings, actions_taken
└─ photos JSON
```

### 3.2 Features in this phase

- **Device CRUD** with catalog-driven forms (specs auto-filled from chosen model; override per-unit).
- **Asset tag QR codes** — generate printable labels (`asset_tag` encoded); scanning opens the device page. Field techs scan → instant device history on phone.
- **Site detail page gains "Equipment" tab** — everything installed at the site with roles and install dates.
- **Inventory views**: stock room counts per type, deployed vs spare ratio, warranty-expiry report.
- **Extend Excel importer**: new template `Devices.xlsx` (model, serial, MAC, assigned site code) reusing `FreewifiImportBatch` machinery.

---

## 4. Phase 2 — Live Health Monitoring

Turn manual UP/DOWN entries into automated telemetry, while keeping the human approval workflow as an override.

### 4.1 Ingestion paths (implement in this order)

1. **Heartbeat API** (simplest, works everywhere) — `POST /api/v1/heartbeat`
   Auth via Sanctum token minted per site/device. Payload:
   ```json
   { "site_code": "AP-XXX", "device_serial": "...",
     "uptime_s": 86400, "firmware": "v2.1", "cpu_pct": 12, "mem_pct": 41,
     "wan_latency_ms": 23, "clients_connected": 14,
     "bw_rx_mbps": 18.4, "bw_tx_mbps": 6.2,
     "power": { "source": "solar", "battery_v": 13.1, "solar_w": 45 } }
   ```
   A cron job on the router/AP (or the ISP's CPE management) POSTs every 5 min. Missing heartbeats ⇒ offline detection.

2. **Controller polling** (when sites are reachable from NOC): scheduled Laravel command pings/SNMP-GETs each site's gateway (`OIDs`: sysUpTime, ifHCInOctets, ifHCOutOctets, hrProcessorLoad). Runs via `schedule:everyFiveMinutes()`, writes same metric rows.

### 4.2 New tables

```
device_metrics        — raw time-series (device_id, ts, uptime_s, cpu_pct, mem_pct,
                        latency_ms, clients, rx_mbps, tx_mbps, battery_v, solar_w, raw JSON)
                        partitioned/indexed on (device_id, ts); aggregate-and-prune after 90 days
site_status_events    — transitions (site_id, from UP→DOWN, started_at, resolved_at,
                        cause enum: heartbeat_lost, poll_failed, manual, planned_maintenance)
alert_rules           — name, metric, operator, threshold, duration_minutes, severity,
                        notify_roles, is_active
alerts                — fired instances (rule_id, site_id, device_id, triggered_at,
                        acknowledged_at/by, resolved_at, escalation_level)
```

### 4.3 Features in this phase

- **Offline detection**: no heartbeat for X min ⇒ auto `SiteStatusEvent` + alert. Auto-resolves when heartbeat returns.
- **Alert rules engine** (seeded defaults):
  - Site offline > 10 min → **critical**
  - Latency > 150 ms for 30 min → warning
  - Bandwidth utilization > 85% of CIR for 60 min → warning (congestion)
  - Battery < 11.8 V → critical (off-grid sites dying at night)
  - Firmware older than latest-approved → info
- **Notifications**: email first; SMS via your existing **jelite SMS API** for critical alerts to field techs by territory; Telegram/Discord webhook optional for NOC.
- **Dashboard rework → NOC wallboard**: big live counters (Online / Down / Degraded / No-data), Philippine map with sites colored by real-time state, active-alerts feed, "longest currently down" list. Auto-refresh via polling or Livewire-free Inertia partial reloads every 60 s.
- **Charts**: per-site/per-device time-series (uptime %, users, throughput, latency, battery). Use lightweight `Chart.js` or `uPlot` (large series).
- **Auto-fill `site_daily_statuses`** from metrics nightly; humans keep APPROVED/LOCKED override for reporting integrity.

---

## 5. Phase 3 — Operations & Reporting

- **Maintenance tickets** (lightweight, built-in): issue → assign field tech → resolve w/ photos + downtime minutes. Feeds MTTR stats. (Deep integration with your `pred-ticket-rag` can come later.)
- **SLA reports**: monthly availability per site/district/province vs target (e.g., ≥97%), users served, data delivered, incidents count, MTTR — one-click PDF extending existing DomPDF reports.
- **Firmware fleet view**: which units run which versions, rollout tracking.
- **Power analytics** for solar sites: battery overnight curves, days-to-failure prediction.
- **Field inspection mobile form**: responsive page (scan QR → checklist → photo upload → GPS confirm).
- **Public transparency mini-portal** (optional, unauthenticated): live map of active sites + basic stats — good PR for the program.

## 6. Cross-cutting Improvements

| Area | Now | Target |
|---|---|---|
| Database | SQLite default | MySQL for prod concurrency (time-series inserts); keep SQLite for dev |
| Queues | database driver | keep; ensure `queue:work` runs as a service (deploy.sh) |
| Scheduler | not used | `schedule:run` cron — heartbeats check, aggregation, digests |
| Time-series volume | n/a | 90-day raw retention → hourly rollup table; charts read rollups |
| Security | Sanctum present, RBAC done | token-per-site for heartbeats; rate-limit ingest endpoint; audit all device mutations (AuditLog exists) |
| Testing | phpunit scaffolded | feature tests for ingestion, alert engine, rollups |

## 7. Suggested Roadmap & Effort

| Phase | Scope | Est. effort |
|---|---|---|
| **P0** | Strip non-FreeWiFi programs (seeder ships 9: PNPKI, eGov, GovNet…) — single-purpose FreeWiFi project; env hardening (MySQL); demo seeders | 1–2 d |
| **P1** | Device registry + QR + imports + Equipment tab | 1.5–2 wk |
| **P2a** | Heartbeat API + metrics storage + offline detection | 1 wk |
| **P2b** | Alerts engine + notifications (email/SMS) + NOC dashboard + charts | 2 wk |
| **P2c** | SNMP polling option + nightly rollups + auto daily-status fill | 1 wk |
| **P3** | Tickets, SLA reports, firmware fleet, power analytics, inspections | 2–3 wk |

**Quick wins first week:** NOC-style dashboard counters from existing `site_daily_statuses`,
offline-alert prototype off the heartbeat API with one pilot router, device tables + Excel import.

---

*This plan keeps every existing feature that serves the Free WiFi program and adds the missing
device layer plus automation — turning a project-tracker into a network operations platform.*
