# DICT FreeWiFi Monitor

Internal operations platform for the Philippines' **DICT "Free WiFi for All / Broadband ng Masa"** program: tracks projects, public WiFi sites, daily UP/DOWN statuses, device inventory with QR asset labels, Excel bulk imports, coverage maps, and PDF reports.

## Stack

- Laravel 11 (PHP 8.2+), Inertia + Vue 3, Tailwind CSS
- MySQL in production, SQLite for local dev/tests
- Database-backed queues (Excel imports, PDF report generation)

## Quick start (Windows / XAMPP)

```powershell
powershell -ExecutionPolicy Bypass -File setup.ps1
php artisan serve
```

`setup.ps1` installs dependencies, creates the SQLite DB, migrates + seeds, generates a **random admin password** (printed once at the end), and builds the frontend.

## Roles & permissions

Seeded by `RolePermissionSeeder` (re-runnable). Route writes are gated via `can:` middleware → Policies → `User::hasPermission(name, projectId)`. Permissions marked *scoped* only apply to projects a user's role assignment is attached to (`role_user.project_id`; `NULL` = global). Read APIs and the map GeoJSON additionally filter rows to `User::accessibleProjectIds()`.

Public self-registration is **off by default** (`REGISTRATION_ENABLED=false`); provision accounts via `users.manage`, `user:make`, or `setup.ps1`. If signup is ever enabled, new accounts start **inactive** until an administrator activates them. Probe tokens require `daily.create`, carry only the `heartbeat` ability, honor the token owner's project scope on every beat, and expire after `SANCTUM_EXPIRATION` minutes (default 30 days).

| Permission | admin | project_manager | encoder | viewer | auditor |
|---|:-:|:-:|:-:|:-:|:-:|
| sites.create / edit¹ / delete¹ | ✓ | ✓ | – | – | – |
| devices.create / edit / delete / view | ✓ | ✓✓✓✓ | view | view | view |
| daily.create / edit / submit / approve / view | ✓ | ✓ | ✓✓✓–view | view | view |
| accomplishment.* | ✓ | full | create/edit/submit/view | view | view |
| milestone.manage | ✓ | ✓ | – | – | – |
| import.excel | ✓ | ✓ | – | – | – |
| reports.view / export | ✓ | ✓ | view | view | ✓✓ |
| users.manage / audit.view | ✓ / ✓ | – | – | – | audit |

¹ Project-scoped — a manager assigned to project A cannot edit or delete sites of project B.

## Daily status workflow

`DRAFT → SUBMITTED → APPROVED → LOCKED`. Editing an APPROVED entry requires `daily.approve`; LOCKED rows are immutable by policy.

## Imports

Upload `.xlsx/.xls/.csv` (≤ 10 MB) on the Import page; parsing runs on the queue (`ProcessExcelImport`), one transaction per row. Devices auto-generate asset tags `FW-####` when blank. Uploaded files are deleted after processing.

## Reports

PDF generation is queued (`GenerateReport`) because large province exports can outlive a web request. Track progress under "Your recent reports"; files auto-expire after 7 days (`reports:cleanup`).

## Scheduled jobs

Requires one cron entry on the server (`artisan schedule:run` every minute). Long jobs carry `withoutOverlapping()` guards.

| Command | Schedule | Purpose |
|---|---|---|
| `imports:cleanup` | every 15 min | Fail imports whose worker died |
| `alerts:down` | every 15 min | Email/Telegram DOWN (+DOWN_SERVER) alerts, once per episode |
| `alerts:evaluate` | every 5 min | Evaluate alert rules, auto-resolve recoveries |
| `metrics:aggregate` | hourly at :10 | Roll device metrics into hourly buckets |
| `statuses:remind` | 07:00 daily | Encoder reminder mail |
| `statuses:snapshot` | 23:00 daily | NO_DATA snapshot (UP derived from heartbeats first) |
| `reports:cleanup` | 01:30 daily | Delete expired PDFs + rows |
| `backup:clean` | 02:00 daily | Prune old backups |
| `backup:run` | 02:15 daily | DB + storage backup (`mysqldump` required) |
| `backup:monitor` | 08:00 daily | Backup health check |
| `metrics:prune` | 03:00 daily | Prune raw telemetry past retention |
| `audit:prune` | monthly | Prune audit rows past retention (default 90 days) |
| `sanctum:prune-expired` | monthly | Prune expired API tokens |
| `warranty:digest` | Mon 07:00 | Email/log devices expiring within 30 days |

## Deployment (Hostinger / CloudPanel)

See `deploy.sh`. Summary:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Production notes:

- Set `DB_CONNECTION=mysql` + credentials; all raw SQL is driver-aware (MySQL/SQLite).
- Run `php artisan queue:restart` after every deploy.
- Error monitoring: set `SENTRY_ENABLED=true` + `SENTRY_LARAVEL_DSN`.
- Never reuse credentials from this repo — `setup.ps1` generates random ones.

## Quality gates

```bash
composer test      # PHPUnit feature/unit suite
composer analyse   # PHPStan/Larastan level 5
composer lint      # Pint style check
npm run lint       # ESLint (Vue, zero warnings allowed)
```

CI runs all of the above on every push/PR (`.github/workflows/ci.yml`).

## Architecture map

```
app/
├── Http/Controllers        Thin; Inertia responses + redirects (API: permission-gated reads)
├── Http/Requests           All validation (FormRequests)
├── Http/Middleware         Inertia sharing, audit logging, security headers
├── Services/
│   ├── DeviceDeploymentService   Device lifecycle + assignment history (one open deployment max)
│   ├── ImportService             Sites/devices/workbook Excel upserts (respects APPROVED/LOCKED)
│   ├── ReportingService          Dashboard stats + PDF views (chunked queries)
│   ├── GeoJsonService            Leaflet feed (5-min cache, 10k-feature cap)
│   └── Telegram                  Fire-and-forget ops alerts
├── Jobs/                   ProcessExcelImport, GenerateReport
├── Console/Commands        Scheduler workers (alerts, snapshots, pruning, backfills)
├── Models/                 Generic phpstan-documented relations
├── Observers/              Audit trail + accomplishment history (shared request_id)
└── Policies/               RBAC enforcement (project-scoped)
routes/web.php              All can: gates live here
routes/api.php              Sanctum reads (permission-gated) + heartbeat ingest
resources/js/Pages          Inertia pages (Vue 3)
docs/FREEWIFI_MONITORING_PLAN.md   Roadmap (heartbeat API, NOC wallboard…)
```
