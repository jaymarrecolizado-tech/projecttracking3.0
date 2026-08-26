# Plan — Daily Site Status Updates

**Goal:** every active site gets an accurate daily `site_daily_statuses` record through two channels — automated heartbeat pushes and a fast manual **Daily Ops Board** — under the full DRAFT → SUBMITTED → APPROVED workflow. Silent sites are auto-marked `NO_DATA` at end of day.

**Status legend:** ☐ pending · 🔄 in progress · ✅ done

---

## Step 1 — Daily Ops API ✅
- [x] `app/Http/Controllers/DailyOpsController.php`
  - `GET /daily-ops` — date/project/province scoped payload; sites filtered by the user's project-scoped `daily.view`; includes each site's record for that date + reported/total counts
  - `POST /daily-ops/batch` — transactional upserts; per-entry authorization (`daily.create` new / `daily.edit` edit / `daily.approve` for APPROVED rows); LOCKED rows rejected
- [x] `app/Http/Requests/BulkDailyOpsRequest.php` — validation + `action` (`save_draft|submit|approve`)
- [x] Routes registered in `routes/web.php`

## Step 2 — Daily Ops Board UI ✅
- [x] `resources/js/Pages/DailyOps/Index.vue`: date picker, project/province filters, municipality-grouped rows, UP/DOWN/NO NMS toggles, bandwidth/users/remarks inline, Save Draft / Submit / Approve buttons by permission, "mark remaining UP", live progress counter
- [x] Sidebar nav entry (Monitoring group)

## Step 3 — Scheduler ✅
- [x] `statuses:remind` (07:00) — emails per-project encoders their unreported site count + link
- [x] `statuses:snapshot` (23:00) — inserts `NO_DATA` DRAFT rows for active sites missing today's record (idempotent)
- [x] Registered in `routes/console.php`

## Step 4 — Heartbeat hardening ✅
- [x] `POST /api/heartbeat` returns **409** instead of overwriting LOCKED rows

## Step 5 — Tests ✅
- [x] Ops board scopes sites to the encoder's assigned projects
- [x] Submit flow sets `SUBMITTED` + `submitted_at`
- [x] Approve requires `daily.approve` (encoder attempt skipped)
- [x] LOCKED rejects manual edits *and* heartbeats (409)
- [x] Snapshot idempotent, skips non-active sites
- [x] Reminder mail sent to project encoders

## Step 6 — Quality gates & commit ✅
- [x] Pint · PHPStan · ESLint · vite build · full test suite
- [x] Single feature commit

## Backlog (not this pass)
SMS notifications · `nms:pull` SNMP/CMS interface stub · DataTable shared component · Sites/Index search filters · DB backups · accessibility pass
