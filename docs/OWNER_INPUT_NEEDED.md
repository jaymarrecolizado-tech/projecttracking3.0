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

### 1A. Decided 2026-09-28 — deliberately left unset *(no action needed)*

These were reviewed and the **unset** state is the decision, not a gap. The code is already
in this state; the point of recording it is so nobody "fixes" it by guessing a number.

| # | Decision | Verified state | Why unset is correct right now |
|---|---|---|---|
| 1.4 | **SLA uptime target** stays unset | `config('monitoring.sla_uptime_target')` → `NULL` | No figure has been confirmed by DICT. Reports state "No uptime SLA target is configured, so this period is not marked pass or fail" — the honest output. **Revisit when DICT gives a number** |
| 1.5 | **`NO_NMS` counts against uptime** | already in `config/daily_status.php` → `observed` | A site with no NMS reporting is a site not *proven* up. Crediting it would make the headline describe a smaller fleet than the program has. To reverse later: remove it from `observed` — one edit moves it out of uptime, trends and SLA together |
| 1.2 | **Survey escalation threshold** stays unset | `config('monitoring.survey_escalation.mean_below')` → `NULL` | A threshold picked without data is a guess. The digest sends nothing and says so on the console. Set both halves together when there are enough responses for a mean to mean something |
| 1.6 | **Audit retention stays 90 days** | hardcoded `--days=90` in `PruneAuditLogs` | No contrary requirement on record. ⚠️ **This is a code constant, not a config key** — changing it means passing `--days=N` in `routes/console.php:22` or editing the command signature. Confirm 90 days satisfies the audit requirement before cut-over |

### 1B. Still needs a value from you *(these cannot be defaulted)*

| # | Question | Where it goes | Consequence of leaving it blank |
|---|---|---|---|
| 1.1 | **Sentry DSN** for production | `SENTRY_LARAVEL_DSN` in prod `.env` | Production errors go nowhere. Package is installed and wired |
| 1.3 | **Who receives the survey escalation digest** | `SURVEY_ESCALATION_EMAIL` | The digest stays inert until this *and* 1.2 are both set |
| 1.7 | **Survey response retention** — 24 months then aggregate-only? | survey config | Responses are personal-adjacent (comments, IP hash) and are **also copied into generated PDFs and CSVs** — a PDF in someone's inbox outlives the row |

### 1C. Found while verifying 1A — needs attention at cut-over

⚠️ **The notification layer has no configured channel.** Verified locally: `WATCHDOG_EMAIL`
is unset, and so are `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID`. Consequences:

- `alerts:down` still mails **users holding `daily.approve`** on the owning project, but has
  no catch-all address — so if no such user is watching, a site going DOWN notifies nobody.
- `alerts:evaluate` has the same shape (approvers + watchdog + Telegram).
- `warranty:digest` sends **nothing at all** with no recipient.
- `reports:scheduled` falls back to the watchdog address, so the monthly pack has nowhere
  to announce itself.

This is a deployment step, not a code change — but it is the difference between an app that
notifies its operators and one that quietly does nothing. Set at least one channel before
real traffic.

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
- [ ] **Set at least one notification channel** — `WATCHDOG_EMAIL` and/or `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID`. ⚠️ Currently neither is configured, so DOWN alerts reach only `daily.approve` holders, `warranty:digest` sends nothing, and the monthly report pack has no recipient. See §1C
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
