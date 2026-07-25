# PenPlus NCD v2 (PPlusV2)

A clinical mentorship evaluation platform for non-communicable diseases (NCDs)
in resource-limited settings. Mentors assess healthcare workers (mentees)
against disease-specific competency tools across repeated mentorship visits,
and the results roll up into district/facility reporting on time-to-competence
and persistent skill gaps.

This is a ground-up rebuild of an earlier Nuxt 3 app, split into two
purpose-built apps that talk to each other through CouchDB.

> **For agent sessions / deep implementation detail:** see [CLAUDE.md](CLAUDE.md).
> It is the authoritative reference for data model rules, gotchas, and
> per-tool item data and is kept up to date for that purpose. This README is a
> human-facing orientation to the repo.

---

## Repository layout

| Path | What it is |
| --- | --- |
| [`monitoring/`](monitoring/) | Nuxt 4 field app for mentors (offline-first, PouchDB-backed) |
| [`reporting/`](reporting/) | Laravel 12 app: CouchDB→MySQL sync, Filament admin, Inertia reporting dashboard, MCP server |
| [`CLAUDE.md`](CLAUDE.md) | Full domain model, architecture rules, gotchas, tool/item catalogue |
| [`PEN-Plus Mentorship Tool. 2.0_April_ 2026.docx/.pdf`](.) | Source paper tool the evaluation items were digitized from |
| [`IMPLEMENTATION_SUMMARY.md`](IMPLEMENTATION_SUMMARY.md), [`SCORE_ANALYSIS_CHART.md`](SCORE_ANALYSIS_CHART.md) | Notes from specific feature builds (score analysis) |

---

## Architecture

```
Mentor (mobile/offline)
   │  fills in session forms
   ▼
monitoring/ (Nuxt 4 + PouchDB)
   │  PouchDB .sync() — one document per session visit
   ▼
CouchDB  ← handoff database between the two apps
   │  polled via /_changes feed
   ▼
reporting/ php artisan sync:couchdb  (scheduled every 5 min)
   │  upserts into MySQL
   ▼
MySQL (penplus_reporting)
   │
   ├─▶ Filament admin panel        (/admin)      — reference data, session/gap CRUD-lite
   ├─▶ Inertia + Vue dashboard     (/)            — 20+ analytical report pages
   └─▶ MCP server                                 — AI-queryable score analysis tool
```

The monitoring app **never writes directly to MySQL** — CouchDB is the only
integration point between the two apps. See [CLAUDE.md §3](CLAUDE.md) for why
sessions are one-document-per-visit rather than the legacy nested model.

---

## Tech stack

### `monitoring/` — Nuxt 4 field app

| Area | Detail |
| --- | --- |
| Framework | Nuxt 4, strict TypeScript, source in `app/` |
| UI | Nuxt UI 4.6.1 |
| State | Pinia 3 |
| Utilities | VueUse 13, date-fns 4, lodash-es 4 |
| Local storage / sync | PouchDB 9 (`pouchdb-adapter-idb`), CouchDB live replication |
| Package manager | pnpm 10 |
| Mobile | Capacitor — planned, not yet added |

### `reporting/` — Laravel 12 reporting & admin app

| Area | Detail |
| --- | --- |
| Framework | Laravel 12, PHP 8.2+ |
| Admin panel | Filament 3 (`/admin`) |
| Reporting dashboard | Inertia 3 + Vue 3, Tailwind 4, ApexCharts |
| AI integration | `laravel/mcp` — score analysis exposed as MCP tools |
| Database | MySQL 8+ |
| Auth | Session-based, custom `Role` model (`admin`, `district_admin`, `evaluator`) |

---

## What's inside `reporting/`

Beyond the CouchDB→MySQL sync, the Laravel app serves three distinct
surfaces off the same database:

- **Filament admin** (`/admin`) — reference data (districts, facilities,
  users), evaluation sessions and gap entries, plus dashboard widgets
  (`TotalMenteesWidget`, `CompetencyRateWidget`, `CompetencyByDistrictWidget`,
  `ActiveJourneysWidget`, `OpenGapsWidget`, `SessionsByToolWidget`) and a
  `ScoreAnalysis` page. `UserResource` also lets an admin **create clinician
  users** (mentors/evaluators) directly — on save, `CouchDbUserPushService`
  pushes the profile to `penplus_users` in CouchDB so it shows up on
  monitoring devices via their normal pull sync. This is currently the only
  way to provision a mentor account; there's no self-service sign-up.
- **Reporting dashboard** (`/`, Inertia+Vue, behind `reporting.auth`
  middleware) — over 20 report pages under `resources/js/pages/Reports/`,
  including journey status/sessions/gaps, low-score watchlist, time-to-competence,
  cohort progress, tool/item analysis, heatmaps, hot spots, high-risk alerts,
  and evaluator activity. Gap editing and score-analysis CSV exports are
  restricted to the `admin` middleware group.
- **MCP server** (`app/Mcp/Servers/ScoreAnalysisServer.php`, registered in
  `routes/ai.php`) — exposes `aggregate_scores`, `item_level_scores`, and
  `score_gaps_analysis` as tools an AI agent can call directly against the
  reporting database.

Scheduled jobs (`routes/console.php`):

```php
Schedule::command('sync:couchdb')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('export:reports --all')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('export:reports journey --filename=weekly')->weeklyOn(1, '06:00')->withoutOverlapping();
```

---

## Core domain rules (short version)

- **Mentee score only** — `menteeScore: 1 | 2 | 3 | 4 | 5 | null`. No mentor/autonomy score.
- **Unlimited sessions** — a mentee+tool "journey" (`evaluationGroupId = ${mentee.id}::${toolSlug}`)
  can have any number of sessions until competency is reached; there's no 5-session cap.
- **`sessionNumber` is never stored** — it's derived by `ROW_NUMBER()` over
  `evalDate, createdAt` in the `v_sessions_numbered` MySQL view.
- **Competency closure** — `basic_competent` (all non-advanced items scored 4–5)
  is the default closing rule; `fully_competent` additionally requires advanced
  items at 4–5. Both are reportable.
- **Tool item slugs are tool-namespaced** (`echo-E1` vs `epilepsy-E1`) because
  display numbers repeat across tools.
- **Gaps are journey-scoped, not session-scoped.**

Full detail, including source-document quirks and known gotchas, is in
[CLAUDE.md](CLAUDE.md).

---

## Getting started

### Prerequisites

- Node.js + pnpm (for `monitoring/`)
- Node.js + npm (for `reporting/` frontend assets)
- PHP 8.2+, Composer (for `reporting/`)
- MySQL 8+
- A running CouchDB instance reachable by both apps

### `monitoring/`

```bash
cd monitoring
pnpm install
pnpm dev
```

### `reporting/`

There is no `.env.example` checked in — create `reporting/.env` with at least:

```env
APP_KEY=                       # generate with `php artisan key:generate`
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ncdpplus
DB_USERNAME=root
DB_PASSWORD=

COUCHDB_URL=http://localhost:5984
COUCHDB_USER=
COUCHDB_PASSWORD=
COUCHDB_DB_SESSIONS=penplus_sessions
COUCHDB_DB_GAPS=penplus_gaps
COUCHDB_DB_USERS=penplus_users
COUCHDB_DB_DISTRICTS=penplus_districts
COUCHDB_DB_FACILITIES=penplus_facilities
```

Then:

```bash
cd reporting
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed          # tools/items catalogue + roles + one demo user per role
npm install
npm run dev          # Vite dev server (frontend assets)
php artisan serve    # in another shell
```

`php artisan db:seed` runs `ToolsAndItemsSeeder`, `RoleSeeder`, and
`RoleUsersSeeder` (all idempotent — safe to re-run). The latter creates one
login per role, all with password `password`:

| Role | Email |
| --- | --- |
| `admin` | `admin@penplus.local` |
| `district_admin` | `district-admin@penplus.local` |
| `evaluator` | `evaluator@penplus.local` |

> If you ever run `php artisan config:cache`, remember to `php artisan config:clear`
> after changing `.env` — otherwise Laravel keeps using the cached values.

### CouchDB databases

CouchDB has no schema/migrations of its own — create the required databases
once per environment:

```bash
php artisan couchdb:setup
```

This reads `config/couchdb.php` and `PUT`s each configured database
(`penplus_sessions`, `penplus_gaps`, `penplus_users`, `penplus_districts`,
`penplus_facilities`), skipping any that already exist. Note that
`penplus_facilities` is currently unused in practice — facility names are
embedded inside district documents and extracted by `sync:couchdb`.

### Sync CouchDB → MySQL

```bash
php artisan sync:couchdb              # all databases
php artisan sync:couchdb --db=sessions
php artisan sync:couchdb --reset      # re-sync from the beginning
```

This needs to run automatically for districts/facilities/users/sessions/gaps
created on monitoring devices (or districts inserted directly into CouchDB)
to ever reach MySQL:

- **Scheduled** — `routes/console.php` runs `sync:couchdb` every 5 minutes,
  but Laravel's scheduler only fires if something is actually invoking
  `schedule:run` on a timer. In production, add a cron entry:
  ```
  * * * * * cd /path/to/reporting && php artisan schedule:run >> /dev/null 2>&1
  ```
  On Windows (this dev setup), a Scheduled Task named
  `PenPlusReportingScheduler` runs `php artisan schedule:run` every minute —
  check it with `Get-ScheduledTask -TaskName PenPlusReportingScheduler` /
  `Get-ScheduledTaskInfo` in PowerShell, or via Task Scheduler → Task
  Scheduler Library. Remove it with
  `Unregister-ScheduledTask -TaskName PenPlusReportingScheduler`.
- **On demand** — an admin can trigger a sync immediately from the Filament
  panel at **`/admin/couchdb-sync`** ("CouchDB Sync" page, admin-only), which
  shows each logical database's last-synced time and a "Sync Now" /
  per-database "Sync" button. Useful right after seeding CouchDB directly, or
  when you don't want to wait for the next 5-minute tick.

---

## Where things stand

See [CLAUDE.md §7 "Current State"](CLAUDE.md) for the maintained status of
both apps. At a high level: the monitoring field workflow is complete end to
end (session capture, journeys, gaps, sync, storage cleanup); the reporting
app has grown well past its original Filament-only scope into a full Inertia
reporting dashboard with dozens of report views plus an MCP server for
AI-driven score analysis.
