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

## 🔲 Backlog (priority order)
1. **Deploy to Hostinger** — first real MySQL run: `mysqldump` on PATH for backups, `queue:restart` after deploy, cron `schedule:run`
2. **SMS/Telegram alerts** — needs a gateway choice (ClickSend/Twilio/Telegram bot); hook into `alerts:down`
3. **`device_metrics` time-series** — heartbeat currently stores one row/day; add high-frequency table + charts (docs Phase 2)
4. **DataTable shared component** — extract from Sites/Devices/DailyGrid tables
5. **Accessibility** — remaining: focus trap in mobile drawer, aria-live on ops counter, table captions
6. **User management UI** — `users.manage` permission exists but no admin screen for users/role assignment
7. **2FA for admin accounts** — Laravel Fortify or TOTP package
