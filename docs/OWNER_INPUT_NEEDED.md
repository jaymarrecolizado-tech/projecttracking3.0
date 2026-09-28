# Owner input needed — DICT FreeWiFi Monitor

**Prepared:** 2026-09-28 · Companion to `Plan.md`

Every remaining item across Plans #1–#7 is on this page. None of it is unfinished engineering —
the feature work is code-complete and the gates are green. What is left needs a **decision**,
a **secret**, or **access to a real server**, because the honest failure mode for each is
inventing a plausible answer and shipping it (a wrong SLA verdict, an escalation email to the
wrong people, an integration against an API that does not exist).

Ordered by "how much does this unblock".

---

## 1. One-line answers — unblock code that is already built

| # | Question | Where it goes | Consequence of leaving it blank |
|---|---|---|---|
| 1.1 | **Sentry DSN** for production | `SENTRY_LARAVEL_DSN` in prod `.env` | Production errors go nowhere. Package is installed and wired |
| 1.2 | **Survey escalation threshold** — mean rating below which a site is chased | `SURVEY_ESCALATION_MEAN_BELOW` | `survey:escalate` stays inert and says so. No wrong emails sent |
| 1.3 | **Who receives that digest** | `SURVEY_ESCALATION_EMAIL` | Same — inert until both are set |
| 1.4 | **SLA uptime target %** (e.g. 97) | `SLA_UPTIME_TARGET` | Reports state "no SLA target configured" instead of printing a pass/fail against a number nobody agreed to |
| 1.5 | **Does `NO_NMS` count against the SLA?** | `config/daily_status.php` → `observed` | Affects every uptime/trend/SLA figure at once — one edit moves the status out of the denominator everywhere |
| 1.6 | **Audit trail retention** — is 90 days the requirement? | `AUDIT_RETENTION_DAYS` | `audit:prune` runs monthly regardless; the trail may be deleted before an audit needs it |
| 1.7 | **Survey response retention** — 24 months then aggregate-only? | survey config | Responses are personal-adjacent (comments, IP hash) and are **also copied into generated PDFs and CSVs** — a PDF in someone's inbox outlives the row |

---

## 2. Schema decisions — these block code from being written

`survey:escalate` currently only mails. The "auto-raise a ticket" half **cannot be written
honestly yet**, for two concrete reasons:

| # | Question | Why it blocks |
|---|---|---|
| 2.1 | **What should a satisfaction complaint be called** in `maintenance_tickets.category`? | It is a fixed **enum** with no `satisfaction` value — needs a migration, i.e. a domain decision about how this class of ticket is categorised |
| 2.2 | **Which system account owns machine-raised tickets?** | `reported_by` is **NOT NULL**. A scheduled command has no user to attribute a ticket to and would have to invent one |

---

## 3. Vendor decisions — build nothing until these are answered

| # | Question |
|---|---|
| 3.1 | **Which of the 12 providers are actually in scope for live NMS polling?** For each: API type, endpoint, auth, and whether the interface exists *yet* |
| 3.2 | **Who gets paged when `/up` goes down**, and via what channel? (an uptime monitor needs a destination) |
| 3.3 | **On-call phone numbers** for the incident runbook's escalation table — currently deliberately blank. A role is not a phone number; inventing one is worse than the blank |
| 3.4 | **Is Telegram enough for SMS?** If not, a provider (ClickSend/Twilio) sits beside `App\Services\Telegram` |

> **Why 3.1 is not being guessed at:** the fleet is 12 companies across 4 transports
> (PHILCOMSAT 280 LEO + 108 VSAT; DICT 325 RADIO + 16 LEO + 8 FIBER; everything else
> single-tech). `NmsClient` is single-bound and `nms:pull` polls every site in one call, so
> binding one client today would send all 1,132 site codes to one vendor's API. A wrong guess
> produces a plausible-looking integration against an endpoint that does not exist.

---

## 4. Needs a real server — cannot be done from a laptop

| # | Task | Notes |
|---|---|---|
| 4.1 | **Restore drill** — restore last night's dump into a scratch DB, boot the app against it, time it, record what was wrong | Steps in `docs/DEPLOY.md` §5.6. **A backup that has never been restored is a hypothesis.** This is the one drill that genuinely needs the server |
| 4.2 | **Prove supervisor restarts a killed worker** | The *code path* is now an automated test; the actual process restart is not |
| 4.3 | **Confirm production MySQL version + `sql_mode`** | CI proves MySQL 8 with strict modes; the *production server's* actual setting is unconfirmed |
| 4.4 | **Print one survey QR placard and scan it on a phone** | The generator is covered by tests; a real print-and-scan is not. Before the field run |
| 4.5 | **Create the external uptime monitor** pointed at `/up`, 1-minute interval | Needs an account. `/up` now fails on unreachable DB or unwritable `storage/`, so it means "available", not "PHP answered" |

---

## 5. Deploy checklist — run at cut-over

Deployment is **on hold** (owner decision 2026-09-14) until §1–§4 are closed.

- [ ] `php artisan db:seed --class=SiteSurveySeeder` (idempotent) — **deploys the question set**
- [ ] `php artisan sites:backfill-regions` — **must** report 100%. The migration does *not* do this; it runs before the workbook import and matches zero rows
- [ ] `php artisan providers:normalize --apply` (optional; dry-run by default, reports unmapped values)
- [ ] Sync Vite `public/build` to **both** the domain folder and `fpiap-app/public`
- [ ] `php artisan queue:restart` after every deploy
- [ ] `deploy.sh` assumes a standard `public/` docroot — the CloudPanel split layout needs the extra copy step by hand
- [ ] Set the §1 values **before** first real traffic, so no report is generated against blanks

---

## 6. Deliberately not being built

Not on the critical path, and recorded so nobody re-litigates them:

- **Playwright / E2E** — declined. `PageSmokeTest` already covers the HTTP layer; Playwright
  would add browser binaries and a JS-capable CI runner for no defect found in review.
- **Solar power analytics** — `solar_w` is captured by the heartbeat but almost never sent.
  The section would print a column of zeros. Wait for probes to report it.
- **Field inspection form** — no spec exists. Building blind means rework.
- **Public unauthenticated map** — publishing exact coordinates of government infra is a
  security call, not a build task.
- **TOTP hard-require** — needs a rollout plan so existing accounts are not locked out.
