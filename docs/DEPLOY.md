# Deploy runbook — Hostinger CloudPanel (first MySQL run)

This is the checklist for putting FPIAP · FreeWiFi Monitor on the production
Hostinger VPS. `deploy.sh` automates the repeatable part; this document covers
the one-time setup and the things only a human with server access can do.

**Something is on fire, not deploying?** See `INCIDENT_RUNBOOK.md` — what each
alert means, what to check first, and what to do when the queue is stuck.
`USER_GUIDE.md` is the console guide for encoders and managers.

## 1. One-time server setup (CloudPanel)

1. **Site** — create a PHP 8.3 site for the domain. Document root must point at
   `…/htdocs/<site>/public`, not the project root.
2. **Database** — create a MySQL database + dedicated user. Note the
   credentials for `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. **PHP CLI** — confirm `php`, `composer`, `node`, `npm`, and **`mysqldump`**
   resolve on the site user's PATH. `mysqldump` is required by the nightly
   `backup:run` (02:15); `deploy.sh` warns when it is missing.
4. **Get the code** — clone/upload the repo into the site dir, copy
    `.env.production.example` to `.env` and fill it in:
    - `APP_KEY` — generate with `php artisan key:generate` after copying.
    - `APP_URL` — the real domain. Set `ASSET_URL` only if assets come from a
      CDN or alternate host (route links are relative via Ziggy, so they work
      from any host).
    - Keep `REGISTRATION_ENABLED=false` (accounts are provisioned by admins).
    - Keep `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true`.
    - Set `SANCTUM_EXPIRATION` (probe-token lifetime, minutes) to a rotation
      your field team can live with.
    - Leave `CORS_ALLOWED_ORIGINS` empty unless a browser app calls `/api/*`.
    - Mail, `WATCHDOG_EMAIL`, `TELEGRAM_*` when used.
5. **First provisioning** — `bash deploy.sh` (it runs composer --no-dev,
    `npm ci && npm run build`, an optional pre-migration DB backup,
    `migrate --force`, caches, `queue:restart`).
    On the split CloudPanel layout, export `PUBLIC_BUILD_TARGET` to the domain
    folder so `deploy.sh` syncs `public/build` there, e.g.
    `PUBLIC_BUILD_TARGET=/home/<site-user>/htdocs/<domain>/build bash deploy.sh`.
    Then seed baseline roles/permissions if a fresh DB:
    `php artisan db:seed --force`.

## 2. Cron (user crontab, every minute)

Laravel's scheduler drives reminders, snapshots, DOWN alerts, backups, and
pruning — one cron entry runs them all:

```
* * * * * cd /home/<site-user>/htdocs/<site> && php artisan schedule:run >> /dev/null 2>&1
```

## 3. Queue worker

Imports (Excel) and PDF reports are queued. A Supervisor program is committed
at `deploy/fpiap-worker.conf` — copy it to the supervisor drop-in dir,
fill in `<site-user>`/`<site>`, and `supervisorctl reread && supervisorctl update`:

```
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Liveness: `php artisan queue:check` exits non-zero when PENDING work is older
than 5 minutes (same signal as the amber Reports-page banner). Point the
external uptime monitor at it, or add a cron that pages on failure:

```
*/5 * * * * cd /home/<site-user>/htdocs/<site> && php artisan queue:check >> /dev/null 2>&1
```

`deploy.sh` already ends with `php artisan queue:restart` so workers pick up
new code after every deploy. If supervisor is not available, a fallback cron
(plus `after failure` restart semantics) is acceptable on a low-traffic VPS:

```
* * * * * (php artisan queue:work --stop-when-empty --max-time=300 >> /dev/null 2>&1)
```

## 4. Scheduled jobs this installs (all Asia/Manila)

| Time  | Job                                   |
|-------|---------------------------------------|
| 01:30 | report export cleanup                 |
| 02:00 | backup cleanup                        |
| 02:15 | DB backup (`mysqldump` required)      |
| 03:00 | telemetry prune                       |
| 03:20 | log file retention (`logs:prune`)      |
| monthly (1st 03:30) | audit log prune           |
| monthly (1st 04:00) | expired token prune         |
| monthly (1st 06:00) | provincial report packs     |
| 07:00 | encoder reminder / warranty digest (Mon) |
| 08:00 | backup health monitor                 |
| 08:30 | low-rating survey escalation (inert until configured) |
| 15 min| DOWN alerts, import cleanup, Telegram alerts |
| hourly :10 | device metric aggregation        |
| 5 min | alert rule evaluation                 |
| 23:00 | NO_DATA snapshot                      |

Two of these are deliberately quiet no-ops until an owner decision is made, and
both say so when they run: `logs:prune` keeps 30 days of logs, and
`survey:escalate` sends nothing at all until both `SURVEY_ESCALATION_MEAN_BELOW`
and `SURVEY_ESCALATION_EMAIL` are set.

## 5. Production cutover checklist (each release)

1. **Pre-flight** — `git pull` (or upload) the release; confirm `.env` is
   preserved; `PUBLIC_BUILD_TARGET` points at the domain folder on the split
   layout (see §1.5).
2. **Deploy** — `bash deploy.sh` (pre-migration DB dump when `mysqldump`
   exists → maintenance window → `migrate --force` → caches → `queue:restart`).
3. **Backfills** — `php artisan sites:backfill-regions` (region filter
   coverage); `php artisan sites:backfill-districts` only after a fresh
   workbook import; `php artisan providers:normalize --apply` (collapses the
   free-text provider columns onto `config/providers.php`; dry-run by default).
4. **Assets** — `public/build` synced to **both** the app dir and the domain
   folder; `php artisan route:cache` is safe (single `dashboard` name).
5. **Smoke** — `GET /up` 200; log in (deactivated users rejected); Daily Ops
   board loads with today's date; submit one batch entry; `backup:run` once
   manually to verify `mysqldump` end-to-end.
6. **Restore rehearsal** (quarterly, owner) — download the latest encrypted
   backup off the offsite disk (`BACKUP_OFFSITE_DISK`), decrypt with
   `BACKUP_ARCHIVE_PASSWORD` on a non-production host, restore, boot, and
   confirm login + dashboard KPIs. A backup never restored is not a backup.

## 6. Map boundary polygons

Region II boundary GeoJSON ships with the repo in `storage/app/geo/`
(provinces + municipalities subset; source and coverage caveats in
`storage/app/geo/README.md`). They are served through the authenticated
`/map/boundaries` endpoint with a 12h cache. After deploys that touch them:
`php artisan cache:clear`. New regions require adding the corresponding
subsets to those files — do not place full-nation shapefiles there.
