# User Guide — Daily Ops, Approvals, Reports

For encoders, project managers, and NOC watchers. Ops runbook (deploys,
backups, scheduler) lives in `docs/DEPLOY.md`.

## Daily Ops (`/daily-ops`)

Each site needs one status row per day: **UP**, **DOWN**, **SERVER DOWN**,
**NO NMS**, or **NO DATA**.

- **Encoders** — pick the date, set each site's status, **Save draft** to keep
  working or **Submit** when the day is final. Submitted rows go to an
  approver; drafts stay editable.
- **Lifecycle** — `DRAFT → SUBMITTED → APPROVED`, plus `LOCKED` for frozen
  history. Only approvers (`daily.approve`) can approve or lock.
- **Locked/approved rows are final** — edits, batch uploads, imports, and
  even field-probe heartbeats are rejected (409) rather than overwriting
  them. If a locked day is genuinely wrong, ask an admin — there is no
  self-service unlock, by design.
- **Reminders** — missing statuses trigger a 07:00 reminder; at 23:00 the
  system snapshots the day so every site has a row.

## Map (`/map`)

Filter by province → district → municipality → barangay, project, site type,
or site status. Click a boundary polygon to drill in. Bubbles show the site
count and the dominant health share; click a legend chip (Online / Offline /
Unmonitored / Not located) to isolate one bucket. The slider merges or
splits clusters.

## Reports (`/reports`)

Two ways to generate:

1. **Builder** — preset period (7/14/30 days), the same geo filters as the
   map, tick the sections you need (`ops_period`, `fleet`, `incidents`,
   `progress`), one Generate → a single combined PDF.
2. **Per-pack cards** — project summary, province, site-type coverage,
   barangay coverage, plus the four analytic packs individually.

Exports are queued: the row shows PENDING → PROCESSING → DONE/FAILED, then
a download link (PDF kept 7 days) and, for single packs, a CSV companion
of the annex table. If a row sits at PENDING for minutes, the amber banner
means the queue worker is down — tell an admin, don't re-click Generate.
On the 1st of each month at 06:00 the system auto-queues last month's
provincial packs and announces them by mail/Telegram.

Every PDF opens with a one-page executive summary in plain sentences,
then the KPI strip and ops annexes.

## Alerts & tickets

- `/alerts` — active vs resolved, acknowledge + resolve (`daily.approve`
  to act). Rules (offline minutes, latency, battery, bandwidth, outdated
  firmware) are managed on the same page by `users.manage` holders.
- DOWN sites also notify by email and, when configured, Telegram.
- Tickets track field work to resolution; the incidents pack reports
  backlog, MTTA, and MTTR from them — keep tickets current or the
  numbers lie.

## Accounts & safety nets

- Accounts are provisioned by admins; self-registration is off by default.
  Deactivated accounts cannot log in.
- Turn on two-factor auth in Profile — probes and approvals are safer for it.
- Probe tokens (Profile → probe tokens) let field devices post heartbeats.
  Each token only works for its owner's projects; revoke a token the
  moment a device is lost or reassigned.
- Everything audited: user admin, approvals, imports, report downloads.
