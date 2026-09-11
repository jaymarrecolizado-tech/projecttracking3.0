# Deploy runbook — Hostinger CloudPanel (first MySQL run)

This is the checklist for putting FPIAP · FreeWiFi Monitor on the production
Hostinger VPS. `deploy.sh` automates the repeatable part; this document covers
the one-time setup and the things only a human with server access can do.

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

Imports (Excel) and PDF reports are queued. Under CloudPanel/supervisor run:

```
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
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
| monthly (1st 03:30) | audit log prune           |
| monthly (1st 04:00) | expired token prune         |
| 07:00 | encoder reminder / warranty digest (Mon) |
| 08:00 | backup health monitor                 |
| 15 min| DOWN alerts, import cleanup, Telegram alerts |
| hourly :10 | device metric aggregation        |
| 5 min | alert rule evaluation                 |
| 23:00 | NO_DATA snapshot                      |

## 5. Post-deploy smoke test

1. `GET /up` returns 200 (health probe).
2. Log in — deactivated users must be rejected at login.
3. Daily Ops board loads with today's date; submit a batch entry.
4. `php artisan tinker --execute="echo config('app.name');"` shows the FPIAP name.
5. `php artisan backup:run` once manually — verifies `mysqldump` end-to-end.

## 6. Map boundary polygons

Region II boundary GeoJSON ships with the repo in `storage/app/geo/`
(provinces + municipalities subset; source and coverage caveats in
`storage/app/geo/README.md`). They are served through the authenticated
`/map/boundaries` endpoint with a 12h cache. After deploys that touch them:
`php artisan cache:clear`. New regions require adding the corresponding
subsets to those files — do not place full-nation shapefiles there.
