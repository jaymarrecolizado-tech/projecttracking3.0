# DICT FreeWiFi Monitor

Internal operations platform for the Philippines' **DICT "Free WiFi for All / Broadband ng Masa"** program: tracks projects, public WiFi sites, daily UP/DOWN statuses, device inventory with QR asset labels, Excel bulk imports, coverage maps, and PDF reports.

## Stack

- Laravel 12 (PHP 8.2+), Inertia + Vue 3, Tailwind CSS
- MySQL in production, SQLite for local dev/tests
- Database-backed queues (Excel imports, PDF report generation)

## Quick start (Windows / XAMPP)

```powershell
powershell -ExecutionPolicy Bypass -File setup.ps1
php artisan serve
```

`setup.ps1` installs dependencies, creates the SQLite DB, migrates + seeds, generates a **random admin password** (printed once at the end), and builds the frontend.

## Roles & permissions

Seeded by `RolePermissionSeeder` (re-runnable). Route writes are gated via `can:` middleware → Policies → `User::hasPermission(name, projectId)`. Permissions marked *scoped* only apply to projects a user's role assignment is attached to (`role_user.project_id`; `NULL` = global).

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

Requires one cron entry on the server (`artisan schedule:run` every minute):

| Command | Schedule | Purpose |
|---|---|---|
| `imports:cleanup` | every 15 min | Fail imports whose worker died |
| `reports:cleanup` | 01:30 daily | Delete expired PDFs + rows |
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
composer analyse   # PHPStan/Larastan level 4
composer lint      # Pint style check
npm run lint       # ESLint (Vue)
```

CI runs all of the above on every push/PR (`.github/workflows/ci.yml`).

## Architecture map

```
app/
├── Http/Controllers        Thin; Inertia responses + redirects
├── Http/Requests           All validation (FormRequests)
├── Services/
│   ├── DeviceDeploymentService   Device lifecycle + assignment history
│   ├── ImportService             Sites/devices Excel upserts
│   ├── ReportingService          Dashboard stats + PDF views
│   └── GeoJsonService            Leaflet feed
├── Jobs/                   ProcessExcelImport, GenerateReport
├── Models/                 Generic phpstan-documented relations
├── Observers/              Audit trail + accomplishment history
└── Policies/               RBAC enforcement (project-scoped)
routes/web.php              All can: gates live here
resources/js/Pages          Inertia pages (Vue 3)
docs/FREEWIFI_MONITORING_PLAN.md   Roadmap (heartbeat API, NOC wallboard…)
```
