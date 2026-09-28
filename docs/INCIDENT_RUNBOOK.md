# Incident runbook — FPIAP · FreeWiFi Monitor

**What this is for.** An alert fired, or the console looks wrong, and you have
about ten minutes before someone asks you what happened. This page is the
"now what" companion to `DEPLOY.md` (deploy-time) and `USER_GUIDE.md`
(using the console).

**Two independent alert paths. Know which one fired:**

| You got | Path | Cadence | What it watches |
|---|---|---|---|
| `SITE DOWN` (email / 🚨 Telegram) | `alerts:down` | every 15 min | the site's **latest daily status** being `DOWN` or `DOWN_SERVER` — a human/board-entered status, not the probe |
| `CRITICAL ALERT — <rule name>` | `alerts:evaluate` | every 5 min | `device_metrics` rows written by the **heartbeat probe** |

A `SITE DOWN` means someone reported the site down. A rule alert means the probe
stopped or started telling us something new. Both can be true at once.

**First three moves, in order, for any alert:**

1. Open the **site page** from the link in the alert body — it is in every email
   and Telegram message (`Manage: <url>`). It shows latest status, last daily
   status, installed devices and the 30-day rating.
2. Open **`/alerts`** and **acknowledge** the alert. Acknowledging records who
   saw it and stops it counting as unseen; it does not resolve it.
3. Decide from the table below whether this is a site problem, a probe problem,
   or a backhaul/power problem. Most "site offline" alerts are the third.

---

## 1. What each rule means

Seeded rules (`php artisan db:seed --class=AlertRuleSeeder`; editable at
`/alerts` with `users.manage`). Thresholds below are the seeded defaults —
check the live rule before you trust them.

| Rule | Metric | Fires when | Sev | First diagnostic |
|---|---|---|---|---|
| **Site offline (no heartbeat)** | `offline_minutes` | no `device_metrics` row for the site for >10 min | critical | `Site → Installed Equipment`: is there a device? If not, the probe was never registered — this is a config problem, not an outage. If yes, the AP is not reporting: check power, then upstream. |
| **WAN latency high** | `latency_ms` | latency >150 ms continuously for 30 min | warning | Latency without a DOWN status usually means a degraded backhaul, not a dead link. Check the site page's trend, then ask the provider. Not a field visit yet. |
| **Battery critically low** | `battery_v` | <11.8 V on the latest reading | critical | Power, not network. Check the charge source: grid feed or solar. A site on solar in a run of cloudy days will legitimately hit this — check `solar_w` before dispatching anyone. |
| **Bandwidth congestion** | `bandwidth_pct` | latest daily status's utilisation >85% of the site's CIR | warning | Utilisation comes from the **board entry**, not the probe. Check the recorded number is real; if the site is genuinely over its CIR, that is a capacity conversation, not an outage. |
| **Firmware outdated** | `firmware_outdated` | latest reported firmware is not in `APPROVED_FIRMWARE` | info | Informational, and it stays open until the firmware is recorded. If `APPROVED_FIRMWARE` is unset the rule evaluates to nothing at all — that is a config gap, not a clean fleet. |

**Rules with a duration window** (`duration_minutes`) mean the condition must
have held *continuously* for that long. `duration_minutes = 0` means the rule
keys off the latest reading only, so a single healthy sample closes the alert
automatically. That is why "battery low" clears itself and "latency high"
takes half an hour to clear.

**Alert lifecycle:** opened by `alerts:evaluate` (deduped per rule+site, so one
open alert per pair at a time) → acknowledged by a human → **auto-resolves** on
the first clean evaluation. An alert that will not resolve after the site is
fixed usually means the *metric* is still arriving and still violating — look at
the raw numbers, not the alert.

---

## 2. Who to call

The notification recipients are **roles, not names**, and they resolve from the
database — this is the actual behaviour of `alerts:evaluate` and `alerts:down`:

- `daily.approve` holders on the site's own project (Daily Ops approvers).
  They are the people who can change a status, approve a board entry and resolve
  an alert.
- `WATCHDOG_EMAIL` (from `.env`) as the unconditional fallback.
- Telegram additionally, for everything except `info` severity, when
  `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID` are set.
- `users.manage` holders for the firmware rule.

> **Owner action — fill this in before the NOC depends on the alerts.** The
> roles above are real, but a role is not a phone number. Record the actual
> escalation path here:
>
> | When | Who | How |
> |---|---|---|
> | 07:00–19:00 site fault | Regional IT focal point (name, number) | |
> | After hours critical | On-call engineer (name, number) | |
> | Power / solar / tower fault | Site custodian or LGU contact (per site) | |
> | Backhaul / provider outage | Provider NOC (per provider, see Plan#4) | The twelve operators are listed in `config/providers.php`; which of them has a real NOC contact is still an owner gap |
> | App itself down | Whoever holds server access |

No notification is guaranteed: with no `WATCHDOG_EMAIL` and no Telegram config,
`alerts:evaluate` sends to nobody and the only signal is the wallboard. Check
both are set before trusting an alert channel.

---

## 3. The queue is stuck

**Symptom:** the Reports page shows an amber "stale queue" banner, or a queued
PDF/Excel import sits in `PENDING` forever. Reports and imports run on the
worker, not in the request — **a dead worker looks exactly like a slow report.**

1. `php artisan queue:check` — exits non-zero when `PENDING` work is older than
   5 minutes. Safe to run any time; it only reads. Wire it to a cron (see
   `DEPLOY.md` §3) so you find out without opening the console.
2. Is the worker alive? `supervisorctl status` (unit committed at
   `deploy/fpiap-worker.conf`). Restart with `supervisorctl restart <program>`.
3. Still stuck after the restart → the job is failing, not waiting. Look at
   `failed_jobs`:
   ```
   php artisan queue:failed
   php artisan queue:retry <id|all>
   ```
4. A report that says `FAILED` on the Reports page can be retried from the page
   itself (**Retry**), which clears the error and re-queues it. The error text
   on the row is the exception message — read it before retrying; a retry that
   fails identically is a code or data problem, not a flake.
5. A permanent failure (unknown report type, deleted project) is recorded on the
   export row and **not** retried, deliberately. Fix the cause, then hit Retry.

**Never kill a worker mid-import on production** to "unstick" it. The import is
row-atomic per row (`ProcessExcelImport`), so an interrupted batch keeps the rows
it already committed; killing it does not roll anything back, it just leaves the
batch un-finished. Re-upload the workbook instead.

---

## 4. The console is down (500s, blank pages, `/up` failing)

Ordered by how often it is the cause:

1. **Nothing external was pinging `/up`.** The endpoint now fails on an
   unreachable database or an unwritable `storage/`, so when the monitor is
   configured a 503 means the app genuinely cannot serve a page — but until a
   monitor exists, PHP-FPM or nginx death is still silent. Until then, check
   with the owner.
2. Deploy half-finished. `public/build` missing on the split CloudPanel layout
   makes every page 500 (Vite manifest not found). `DEPLOY.md` §5 covers the
   two-target asset copy.
3. Migrations out of step: `php artisan migrate:status` and run
   `php artisan migrate --force`.
4. Database unreachable. `/up` says so explicitly, and the connection details
   are in `.env` (`DB_HOST`/`DB_PORT`) — MySQL being down or the port changed
   presents identically to the console.
5. Disk full: `df -h`. A full disk stops logs, sessions and report writes at
   once, and the resulting errors look unrelated.
6. `storage/` or `bootstrap/cache` not writable by the site user — permissions
   drift after a manual upload. `/up` reports this (and an unreachable
   database) as a 500 now, so a monitor pointed at it catches it without anyone
   opening the console.
7. Check the log: `storage/logs/laravel-*.log` (daily channel, rotated by the
   app and expired by `logs:prune` — see §5), and `php artisan pail` for a live
   tail.

---

## 5. Logs

`config/logging.php` writes `storage/logs/laravel-YYYY-MM-DD.log` — one file
per day, created by the daily channel — plus `laravel.log` for errors.

**Rotation owner: Laravel's daily channel. Retention owner: `logs:prune`.**
Nothing else touches them, and specifically **not** logrotate: the daily channel
already rotates by file *name*, so a logrotate rule could only ever rename each
day's file once and would then never expire it. A `find`-style prune is the
right tool for a date-named file set, and it is already scheduled:

```
03:20  log file retention (logs:prune, 30 days)
```

Run it by hand any time with `php artisan logs:prune --days=60`. It only
matches `laravel-*.log`, so the file currently being written (`laravel.log`) and
supervisor's `logs/fpiap-worker.log` (capped by the unit's own
`stdout_logfile_maxbytes=10MB`, 3 backups) are untouched.

**Answering "how far back do the logs go?"** is therefore: 30 days by default,
and the audit trail is pruned monthly at 90 days (`audit:prune`). If an
investigation needs more than 30 days of *application* logs, that is an owner
decision, and it must be made together with the audit-retention window in
`Plan.md` F7 — the two numbers should not disagree.

---

## 6. Reporting degradation — the app must not depend on its notifiers

Alert delivery is **not** in the request path for web pages, and it is
fire-and-forget for Telegram (`App\Services\Telegram` catches connection
failures and returns false). So:

- If Telegram or Sentry is unreachable, pages still load and requests still
  succeed. Only the notification is lost.
- `alerts:evaluate` and `alerts:down` run in the scheduler: a hung notifier
  delays them, it does not fail them.
- The one place a lost notification matters is an alert nobody saw. That is why
  the `/alerts` console and the wallboard's active-alert feed exist: they are
  the fallback channel, and they are always on.

This is covered by an automated check (`DegradationTest`) — if a future change
puts a notifier in the request path, that test fails.

---

## 7. Drills worth running once

Both of these are documented as *never having been executed*. They are on the
plan (`Plan.md` → F4) and neither can be done from a laptop:

- **Restore drill (quarterly):** the steps are `DEPLOY.md` §5.6. Restore last
  night's dump into a scratch database, boot the app against it, time it, and
  write the result back into §5.6. A backup that has never been restored is a
  hypothesis.
- **Queue failure drill:** kill the worker mid-import on a non-production copy
  and confirm the batch is marked `FAILED`, the failure is audited, and Retry
  works. The code path is covered by `QueueCheckTest`/`ReportExportTest`; what
  is *not* covered is the supervisor actually restarting a killed worker.
