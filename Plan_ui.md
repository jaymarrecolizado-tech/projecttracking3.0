# Plan_ui — FPIAP FreeWiFi Monitor UI Redesign

Living UI roadmap, driven by the taste-skill bundle
(`redesign-existing-projects` primary, `design-taste-frontend` rules where they apply).
**Done** = merged in the local repo. **Open** = not started.
Companion to `Plan.md` (feature roadmap) — this file owns visual quality only.
No backend, route, policy, or query changes in any slice.

---

## 0. Design Read (taste-skill §0 — read the room first)

> Reading this as: **internal government ops console for DICT field staff, encoders,
> project managers and NOC watchers, with a trust-first dense-data language,
> leaning toward Tailwind utilities + Figtree + Tabler + one teal accent.**

- **Page kinds present:** dense registries (Sites, Devices, Users, Tickets, Alerts,
  Accomplishments), triage boards (Dashboard, Daily Ops, Wallboard), geo tool
  (Map), multi-step flows (Import, Reports, Profile/2FA), auth screens.
  Explicitly **not** landing pages / portfolios — `design-taste-frontend` is
  reference-only here; `redesign-existing-projects` (Scan → Diagnose → Fix) drives.
- **Audience:** operators on laptops and mid-range phones, often low bandwidth,
  scanning for DOWN sites. Legibility and triage speed beat novelty.
- **Quiet constraints (override aesthetics):** public-sector trust, WCAG AA on all
  text/controls, keyboard-only encoders, audit-logged actions. No motion for
  decoration, no palette experiments on status colors.

### Dials (taste-skill §1)

| Surface | VARIANCE | MOTION | DENSITY | Rationale |
|---|---|---|---|---|
| App shell + registries + forms (default) | 3 | 2 | 6 | symmetrical, still, information-dense |
| Login (brand moment) | 6 | 4 | 3 | split identity panel, ledger, restrained |
| NOC Wallboard (distance-glanceable dark) | 4 | 3 | 7 | big readouts, ping only on DOWN dots |

---

## 1. Stack & Token Lock (taste-skill §2–§3 — one system per project)

- **Stack:** Vue 3 + Inertia, **Tailwind v3** (do NOT migrate to v4 — config is v3
  syntax), Figtree (keep — has character, not Inter), `@tabler/icons-vue` **only**
  (one family per tree; never mix in Phosphor/Lucide).
- **No new dependencies** without checking `package.json` first (skill rule).
  No chart library — Dashboard trend bars and Wallboard SVG stay hand-rolled.
- **Accent lock:** `#0E5E6F` (teal) · hover `#0a414c` · ring `#0E5E6F/40`.
  Navy surface `#0F1B2D` (sidebar, wallboard, login panel). Neutrals: slate only,
  never mixed warm/cool gray in one view.
- **Status semantics are data, not accent** — green UP / red DOWN / amber NO_NMS /
  slate NO_DATA, plus blue `info` severity (a 3-state severity scale needs three
  hues). Never recolor these for decoration.
- **Action-color rule (text buttons + fills):** teal = default action/navigation,
  emerald = positive completion (Approve, Resolve, Resolved), red = destructive,
  amber = warning/attention, slate = neutral/secondary. Filled toggles use `-700`
  fills + white text (AA); small text never uses `-500`/`-600` greens/emeralds —
  `-700` instead (pre-flight #1).
- **Shape lock:** `rounded-lg` cards/inputs/buttons everywhere; pills only for
  status badges and severity chips. `min-h-[100dvh]` for full-height auth heroes,
  never `h-screen`.
- **Numbers:** `tabular-nums` on every counter, metric, table figure and sparkline
  label (already true on Dashboard/Login — extend to all slices).

---

## 2. Audit Findings (taste-skill Diagnose — evidence, not vibes)

### 2.1 Palette sprawl — blue accent in ~20 files, indigo/gray Breeze leftovers

| Location | Finding |
|---|---|
| `Sites/Index.vue:69,76,86,96,106,152,174`, `Devices/Index.vue:49,65,98,132,146`, `DailyOps/Index.vue:82,89,99,130`, `Alerts/Index.vue:165,192,244,247,280`, `Tickets/Index.vue:64,75,123,166,215`, `Users/Index.vue` (14 hits), `Sites/Show.vue`, `Devices/Show.vue`, `Import/*`, `Reports/Index.vue:126,282`, `Map/*`, `GeoFilterFields.vue`, `Pagination.vue:21,32` | `blue-500/600` focus rings, links, primary buttons, active pagination, selected rings |
| `PrimaryButton.vue:3`, `SecondaryButton.vue:13`, `TextInput.vue:24`, `Checkbox.vue:32`, `Auth/Register.vue:98`, `Auth/VerifyEmail.vue:55`, `Profile/...UpdateProfileInformationForm.vue:79` | stock Breeze `gray-800` / `indigo-*` / ALL-CAPS buttons |
| `Profile/Edit.vue:38`, `UpdateProfileInformationForm.vue:73` | stray `gray-800/600` text in a slate app |
| `Wallboard.vue:62` | `border-blue-500` + `text-blue-400` "Active Sites" — a neutral magnitude wearing accent color |
| `Dashboard.vue:22` | `severityText.info: text-blue-600` — fine on light (severity scale), keep |

### 2.2 Two status languages

- `StatusPill.vue` (dot + label, `role="status"`, good) exists but `Sites/Index.vue:36-42,136-148`
  rolls its own **filled pills** (`bg-green-100…`), and `Devices`, `Alerts`, `Tickets`
  each inline a third variant. One signal → one component.
- Severity chips (`red/amber/blue` fills) are duplicated in `Dashboard.vue:14-23`,
  `Alerts/Index.vue:32-36`, `Wallboard.vue:99` — extract or mirror exactly.

### 2.3 Auth screens split-brained

- `Login.vue` is premium branded (`#0F1B2D` panel, ledger, tabular readouts).
  `ConfirmPassword / ForgotPassword / Register / ResetPassword /
  TwoFactorChallenge / VerifyEmail` still render stock Breeze `GuestLayout.vue`
  (`bg-gray-100`, gray box, indigo ring). A user sees two products.

### 2.4 Interactivity gaps (hover-only controls)

- Row-click tables (`Sites`, `Devices`) have no `focus-visible` path to the row
  action; icon-only back links (`hover:text-*`, no ring, no `aria-label`).
- Filter-bar buttons/checkboxes, Devices counter-buttons (`ring-blue-500` only when
  selected, no focus ring), Quick-action rows (fixed in Slice 0 — template for the rest).
- Toasts: fine (`aria-live`, focusable dismiss) — keep as reference.
- Wallboard tooltips: none (no hover affordance on SVG bars) — acceptable for a
  passive display; do not add hover-only info.

### 2.5 Type & layout drift

- Page titles vary: `font-semibold text-lg text-slate-800` (most pages) vs
  Dashboard Slice-0 standard `font-bold text-xl text-slate-900 tracking-tight`
  (plus `text-wrap: balance` now global in `app.css`).
- Card spacing varies (`p-4`/`p-5`/`p-6`, `gap-4`/`gap-5`/`gap-6`, `mb-4`/`mb-6`).
  Standardize per surface: filters `p-4 mb-4`, panels `p-6`, grids `gap-6`.
- `Devices/Index.vue:60` section label is ALL-CAPS subhead; form labels are
  uppercase 11px across filters (kept deliberately — see §5 exceptions).

### 2.6 Content & copy (skill §4.9 self-audit backlog)

- Empty states exist but vary in quality (`Sites` good icon+line; `Devices` stock
  line; `Reports` exports unknown — verify each slice).
- Confirm( ) native dialogs (`Alerts/Index.vue:74` delete rule) — replace with the
  app `Modal.vue` pattern per skill (no `window.alert/confirm`).
- Button labels: audit duplicate intent per view on each slice (e.g. "View" vs
  row-click duplication in `Sites/Index.vue:122-157` — keep both only if the link
  is the keyboard path; otherwise the row click + focusable link is the pattern).

---

## 3. Slices (taste-skill Fix Priority order — font → palette → states → layout → components → states → polish)

### Done

- [x] **Slice 0 — Shell + Dashboard + shared CSS** (2026-09-08)
  `app.css` (flat `.dict-card`, smooth scroll, balanced headings);
  `AuthenticatedLayout.vue` (flat `#0F1B2D` sidebar, slate scale, `aria-current`,
  focus/press states); `Dashboard.vue` (flat counters, single-accent lock,
  neutral quick actions, bold tracking-tight headings). Lint: 0 errors.
- [x] **Slice 1 — Accent lock** (2026-09-08)
  All interactive `blue-600/500` → `#0E5E6F` (buttons, links, focus rings,
  selected rings, active pagination, drag states) across ~20 files; Breeze
  `gray-800/indigo` → teal/slate in `Primary/Secondary/DangerButton`,
  `TextInput`, `Checkbox`, `InputLabel`, `Pagination`; Reports 4 card themes →
  one neutral + teal; `DailyGrid` SUBMITTED → amber, Reports PROCESSING → amber
  (in-flight language); Inertia progress bar → `#0E5E6F`. Kept: status fills,
  `info` blue, Wallboard dark, sparkline hues, `IN_PROGRESS` blue.
- [x] **Slice 2 — Auth unification** (2026-09-08)
  `GuestLayout.vue` rewritten in Login split language (navy identity panel +
  `#FAFAF8` form side + audit footer); all six auth screens de-grayed;
  `TwoFactorAuthentication` red override → `DangerButton`. ResetPassword needed
  no changes (pure shared components).
- [x] **Slice 3 — One status language** (2026-09-08)
  `StatusPill` map extended to 40 states (lifecycle, device, ticket, workflow,
  job, priority); new `SeverityChip` (light + dark); migrated Sites, Devices×2,
  Projects×2, Tickets, Accomplishments×2, DailyGrid, Import×2, Users, Dashboard,
  Alerts×2, Wallboard; deleted 5 inline style maps; small-text greens/oranges →
  `-700` AA sweep (incl. DailyOps toggles → `-700` fills).
- [x] **Slice 4 — Registry polish** (2026-09-08)
  Page titles → `font-bold text-xl slate-900 tracking-tight` everywhere;
  card rhythm `p-6/gap-6`; pager focus rings (Sites/Users inline + Devices +
  shared `Pagination`); Devices counters keyboard-safe (total disabled,
  `aria-pressed`, tabular); Devices/Users/Reports empty states upgraded.
  Row-click tables keep the pattern: row-click convenience + focusable View link
  as the keyboard path (duplicate-intent exception, §5).
- [x] **Slice 5 — Flows** (2026-09-08)
  Native `confirm()` → `Modal` in Users delete, Alerts rule delete, Sites
  detach (with asset-tag context); Devices emoji → Tabler icons; Profile cards
  → `dict-card`, partials de-grayed; teal focus tokens on all flow inputs;
  Cancel links focusable; `Modal` overlay → slate; DailyOps toggles/batch bar
  focusable. Bonus fix: Tickets rendered `<Pagination>` without importing it —
  pager was dead; added the missing import.
- [x] **Slice 6 — Wallboard + Map** (2026-09-08)
  Active Sites readout → neutral white (status hues reserved); severity →
  `SeverityChip dark`; 11px timestamps → slate-400; map fallback marker →
  neutral slate (status colors already matched); map container flat.

### Open

(none — all slices implemented 2026-09-08. Lint: 0 errors, remaining warnings
pre-existing in untouched lines.)

---

## 4. Pre-flight Check (adapted from `design-taste-frontend` §12 for ops UI)

Run per slice before marking Done. Any fail blocks the slice.

1. **Contrast AA** on every button, badge, chip and wallboard readout (4.5:1 body,
   3:1 large) — including ghost/back links over panels.
2. **CTA one-liners** — no wrapped button text at desktop; ≤3 words on primaries.
3. **One label per intent** per view (no "View" + "Open" + "Details" for the same action).
4. **Tabular figures** on every metric, count, table number, sparkline.
5. **Focus-visible + press** on every interactive element; `aria-current` on nav;
   never hover-only meaning.
6. **Copy self-audit** — re-read every visible string: no "Seamless/Elevate/Unleash",
   no "Oops!", no lorem, sentence case (except deliberate caps, §5), no fake-precise
   stats unless from real data or labeled mock.
7. **Theme lock** — light app throughout; dark only on Login panel + Wallboard
   (documented intentional, §5). No stray dark sections.
8. **Mobile collapse declared** per multi-column block; sticky elements (`topbar`,
   Daily Ops batch bar) don't cover inputs at 360px.
9. **Lint green** — `npm run lint` 0 errors; no new warnings in touched files.

---

## 5. Deliberate Exceptions (skill rules are contextual — logged, not ignored)

| Rule bent | Where | Why |
|---|---|---|
| ALL-CAPS labels kept | Filter labels, Wallboard section heads, Devices counters | Dense ops convention + distance legibility; never body copy |
| `animate-ping` kept | Wallboard DOWN dots | Motivated motion: draws the eye to outages on a passive display |
| Blue kept | `info` severity, Wallboard dark scale | 3-state severity needs 3 hues; dark-surface blues ≠ light-accent blue |
| Sidebar kept | App shell | Skill suggests alternatives, but 5-group RBAC nav is correct here |
| No font swap | Everywhere | Figtree already clears the Inter-default bar |
| No chart library | Dashboard/Wallboard | Hand-rolled bars/SVG fit the data; a dep adds risk, not taste |
| Row-click + View link kept | Sites/Projects registries | Link is the keyboard path, row-click is convenience — not duplicate intent |
| `report_type` blue/purple badges kept | Projects | Categorical data (two report types), not brand chrome |
| Sparkline series hues kept | Devices/Show | Data-viz needs distinct hues; status dots stay canonical |
| `IN_PROGRESS`/medium blue kept | Tickets/Accomplishments/Alerts | Informational blue in the severity scale, never interactive |
| Unused Breeze shells untouched | `NavLink/Dropdown*/ResponsiveNavLink` | Zero imports; deleting shared components saves nothing, risks confusion |

---

## 6. Slice Prompt Template (copy per slice, replace SCOPE)

```
Use redesign-existing-projects on SCOPE in
C:\xampp\htdocs\Projects\pred-porject-tracking (working folder).

Dials: VARIANCE 3, MOTION 2, DENSITY 6 (Slice 6 Wallboard: 4/3/7).
Stack lock: Tailwind v3, Vue 3, Figtree, @tabler/icons-vue only, no new deps.
Token lock: accent #0E5E6F / hover #0a414c / ring #0E5E6F/40, navy #0F1B2D;
status colors are data, never decoration. See Plan_ui.md §1.

Sequence: Scan (read the files) → Diagnose (list generic patterns with
file:line) → Fix (small targeted diffs, no backend/routes/policies).
Finish with the Plan_ui.md §4 pre-flight check and npm run lint (0 errors).
Update the slice checkbox in Plan_ui.md when done.
```

## 7. Out of Scope

Nationwide shapefiles, live NMS bind, SMS channel, SLA/firmware-fleet/solar
analytics, field-inspection form, public map (all `Plan.md` backlog) — plus any
Tailwind v4 migration, icon-family swap, or dark-mode-everywhere theme.
