# PenPlus NCD v2 - Project Reference

This is the authoritative project handoff file for agent sessions opened in this
workspace. Read it before making changes.

---

## 1. What This Project Is

PenPlus NCD is a clinical mentorship evaluation platform for non-communicable
diseases in resource-limited settings. Mentors assess healthcare workers
(mentees) against disease-specific competency tools.

`PPlusV2` is a ground-up rebuild of the original Nuxt 3 app at:

`C:\Users\Admin\Documents\Projects\Solidarmed\NCD\PenPlus\PenPlusUltimate`

The original app combined both field monitoring and reporting in one Nuxt app.
The rebuild intentionally splits those concerns:

| App | Purpose | Location |
| --- | --- | --- |
| monitoring | Nuxt 4 field app for mentors, intended for mobile/Capacitor | `PPlusV2/monitoring/` |
| reporting | Laravel 12 + Filament management/reporting dashboard | `PPlusV2/reporting/` |

The legacy app used `PenPlusUltimate/data/evaluationItemData.js` for tool data.
The client has replaced/expanded the paper tools in:

`PEN-Plus Mentorship Tool. 2.0_April_ 2026.docx`

The new tool data has been converted into:

`monitoring/app/data/evaluationItemData.ts`

The `_rebuild/` folder in `PenPlusUltimate` contains design artifacts used during
the rebuild, including the target TypeScript interfaces and MySQL schema.

---

## 2. Core Product Decisions

### Mentee score only

The paper tool includes both a mentee competency score and a mentor/autonomy
score row. The client explicitly decided to scrap mentor/autonomy scoring.

The rebuild must store only:

```ts
menteeScore: 1 | 2 | 3 | 4 | 5 | null
```

`null` means N/A or not evaluated. Do not add `mentorScore`, `mentor_score`, or
autonomy score columns unless the client reverses this decision.

The paper legend's **Mentor row** ("I had to do" … "I did not need to be there")
is shown as part of each score's meaning, not as a second score. The client
asked for it on 2026-10-22. `MENTOR_LABELS` in `evaluationItemData.ts` holds the
wording; `ScoreLegend` shows Score / Mentee / Mentor as a table, and `ScoreRow`
shows both meanings, labelled, once a score is tapped, as does its previous-score
line. `ScoreChip` shows both wherever a full score meaning appears (review,
session details, round lists); its `short` form stays one word. Nothing extra is stored.

### Unlimited sessions

The old app treated a complete evaluation as exactly 5 sessions. That cap is
gone.

New rule:

- A session is one mentorship visit.
- An evaluation journey is the combination of `mentee + tool`.
- A mentee can have any number of sessions for a tool until competency is
  reached.
- Once the mentee is competent for that tool, the journey is complete/closed and
  there should be no session `n+1` for that mentee+tool.

The grouping key is:

```ts
evaluationGroupId = `${mentee.id}::${toolSlug}`
```

This key represents the full mentee+tool journey.

### Competency closure rule

Decided 2026-10-22, matching the paper tool and field practice:

- An item is **achieved** once its most recent score in the journey is 4 or 5.
  Mentors don't re-rate achieved items, so that score can come from any earlier
  session. A later lower re-rating makes it not achieved again. N/A never
  replaces an earlier score. The latest score is the latest session's latest
  round that rated the item.
- `basic_competent`: every required (non-advanced) item **in the tool** is
  achieved, not just items evaluated so far.
- `fully_competent`: every item in the tool, including advanced items, is
  achieved.

Implemented in `getCompetencyBreakdown()` (`monitoring/app/composables/useCompetency.ts`)
and in the `v_evaluation_group_status` view
(`2026_10_22_000001_competency_from_latest_item_scores`). Keep the two in step.

The app should default to closing on `basic_competent` because the paper tool
says grey/advanced competencies are not required to be considered competent.
The reporting platform should still expose both metrics.

This enables reporting metrics such as:

- sessions to competence
- days to competence
- first session date
- competent session date
- initial average score vs final average score
- item-level improvement over time
- persistent gaps after repeated sessions
- district/facility/tool comparisons on time to competence

### Session number is never stored

Do not add `sessionNumber` or `session_number` to source documents or base
tables. Compute it dynamically by sorting all sessions in the same
`evaluationGroupId` by `evalDate`, then `createdAt`.

In MySQL this is handled by `v_sessions_numbered` using `ROW_NUMBER()`.

---

## 3. Architecture

### Data flow

- The monitoring app stores field data as PouchDB documents and syncs to CouchDB.
- CouchDB is the handoff/source database between the two apps.
- The Laravel reporting app polls CouchDB `_changes` feeds and upserts into
  MySQL.
- The Nuxt monitoring app must not write directly to MySQL.

### One document per session visit

Legacy model:

- One CouchDB document per mentee+tool.
- Sessions were nested as `session_1`, `session_2`, ..., `session_5`.
- Adding a new session updated the same document, causing CouchDB 409 conflicts
  when multiple devices touched the same mentee+tool journey.

New model:

- One CouchDB/PouchDB document per session visit.
- Documents are write-once/upsert-by-id.
- Multiple sessions are grouped by `evaluationGroupId`.
- This supports unlimited sessions and avoids conflict-heavy nested arrays.

### Tool item slugs

Display numbers are not globally unique:

- Echo and Epilepsy both use `E*`.
- Hypertension and CKD both use `H*`.

Therefore item slugs must be tool-namespaced:

- `echo-E1`
- `epilepsy-E1`
- `hypertension-H1`
- `ckd-H1`

The item `number` remains the paper display code and is only unique within a
tool context.

### Counselling items

DC1-DC9 counselling competencies are evaluated in every session regardless of
selected disease tool, **except Echocardiogram** (client request, 2026-10-22).
`ITool.includesCounselling: false` on the echo tool turns them off; use
`toolIncludesCounselling(tool)` rather than assuming every tool has them. Older
Echocardiogram sessions keep any counselling scores they already have.

- Monitoring stores them in `ISession.counsellingScores`.
- MySQL stores them as normal `evaluation_items` rows under the `counselling`
  tool.
- There is no separate counselling table.

### Echocardiogram activity counts

The two `#` rows in the Echocardiogram paper tool are per-session activity
counts, not competency ratings or gaps:

- `echocardiogramsPerformed`: supervised echocardiograms performed during the
  visit.
- `echocardiogramsCorrectDiagnosis`: supervised echocardiograms with a correct
  final diagnosis during the visit.

The monitoring form collects both counts after competency scoring and before
review. Both must be non-negative whole numbers, and correct diagnoses cannot
exceed the number performed. Counts are stored on the session document, sync to
nullable `evaluation_sessions` columns, and do not affect competency status.

The counts are one total per visit, not per round. The form uses stepper
buttons (`EchoActivityCounts`), the review and session pages show diagnostic
accuracy (`EchoActivitySummary`), and the journey page shows totals and a
per-visit trend (`EchoActivityCard`). Item E8 shows the visit's accuracy as
information only; it never sets a score. See `monitoring/TABLET_UI.md`.

### Advanced items

`isAdvanced: true` marks grey/paper advanced competencies. These are not required
for `basic_competent`, but they should remain reportable.

Items marked advanced in `evaluationItemData.ts`:

- Diabetes: none
- Heart diseases: C14-C15, C19, C32-C36
- Echocardiogram: E7
- Sickle cell: S35-S37
- Respiratory: R4, R23-R24
- Epilepsy: E5-E6
- CKD: H15
- Chronic liver disease: L10

The grey-shaded PDF rows are rendered in charcoal in the plan and observation
screens. Confirm clinical interpretation of these markings before go-live.

### Source document quirks

- Echo: E9 is absent. `echo-E10` follows `echo-E8`.
- Epilepsy: E14 is absent. `epilepsy-E15` follows `epilepsy-E13`.
- Palliative: the document has two items labelled P8. The second P8 is treated
  as P9 in the current data. Confirm with the clinical team before release.

---

## 4. Technology Stack

### monitoring/

| Area | Detail |
| --- | --- |
| Framework | Nuxt 4 (`future.compatibilityVersion = 4`, source in `app/`) |
| UI | Nuxt UI 4.6.1 |
| State | Pinia 3 + `@pinia/nuxt` |
| Utilities | VueUse 13 (`@vueuse/core` + `@vueuse/nuxt`) |
| Date handling | `date-fns ^4.1.0` |
| Utilities | `lodash-es ^4.18.1` (types: `@types/lodash-es`) |
| TypeScript | Strict mode |
| Package manager | pnpm 10.x |
| Local storage | PouchDB 9 + `pouchdb-adapter-idb` (installed, wired via `useDb.ts`) |
| Mobile | Capacitor — planned, not yet added |
| Sync | CouchDB live replication via PouchDB `.sync()` — planned, not yet wired |

pnpm 10 blocks build scripts by default. Keep the `pnpm.onlyBuiltDependencies`
allowlist in `package.json`.

### reporting/

| Area | Detail |
| --- | --- |
| Framework | Laravel 12 |
| Admin | Filament 3 |
| SPA bridge | Inertia Laravel 3 installed |
| Database | MySQL 8+ |
| PHP | 8.2+ |

Laravel must stay on v12 unless Filament compatibility is checked first.

---

## 5. Data Model

### Monitoring TypeScript

`IItemScore`

```ts
export interface IItemScore {
  itemSlug: string
  menteeScore: 1 | 2 | 3 | 4 | 5 | null
}
```

`ISession`

```ts
export type MentorshipPhase = 'initial_intensive' | 'ongoing' | 'supervision'
export type SyncStatus = 'pending' | 'synced' | 'failed'

export interface ISession {
  _id: string
  _rev?: string
  type: 'session'
  evaluationGroupId: string
  mentee: IUserRef
  evaluator: IUserRef
  toolSlug: string
  evalDate: number
  facilityId: string
  districtId: string
  itemScores: IItemScore[]
  counsellingScores: IItemScore[]
  echocardiogramsPerformed?: number
  echocardiogramsCorrectDiagnosis?: number
  phase: MentorshipPhase | null
  notes?: string
  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
```

`IGapEntry`

```ts
export type GapDomain =
  | 'knowledge'
  | 'critical_reasoning'
  | 'clinical_skills'
  | 'communication'
  | 'attitude'

export type SupervisionLevel =
  | 'intensive_mentorship'
  | 'ongoing_mentorship'
  | 'independent_practice'

export interface IGapEntry {
  _id: string
  _rev?: string
  type: 'gap'
  evaluationGroupId: string
  menteeId: string
  evaluatorId: string
  toolSlug: string
  identifiedAt: number
  description: string
  domains: GapDomain[]
  coveredInMentorship: boolean | null
  coveringLater: boolean
  timeline?: string
  supervisionLevel?: SupervisionLevel
  resolutionNote?: string
  resolvedAt?: number
  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
```

Note: `monitoring/app/interfaces/IGapEntry.ts` must include `coveringLater`
because the Laravel schema and sync command expect `covering_later`.

### Reporting MySQL

Implemented migration tables:

- `districts`
- `facilities`
- `users`
- `tools`
- `tool_categories`
- `evaluation_items`
- `evaluation_sessions`
- `session_item_scores`
- `gap_entries`
- `sync_checkpoints`

Implemented views:

- `v_sessions_numbered`
- `v_session_averages`
- `v_evaluation_group_status` (competency rule; see Competency closure rule)
- `v_journey_progress`: one row per session with the journey's **standing** at
  that point, from each tool item's latest score so far: `required_achieved` /
  `required_items`, `total_achieved` / `total_items`, `items_evaluated`,
  `current_avg_score`
- `v_journey_summary`: adds the latest session's standing columns;
  `latest_avg_score` is the last session's average over tool items only
- `v_day_progress`: round-to-round and day-to-day deltas compare only items
  scored in both (`intra_day_items`, `day_over_day_items`)

**Partial evaluation (2026-10-22):** mentors score only the competencies they
observed, so a session's average covers a different item set each time. Never
compare or average session averages across sessions or mentees. Use standing
(`v_journey_progress` / `ReportQueryService::standing()`), item-weighted
averages, or item-by-item deltas. Score trajectory, Cohort progress, Session
report, Gap report, Journey sessions, Struggling mentees, Needs attention,
Journey status and Evaluator activity follow this. Item analysis reports
coverage: journeys and sessions that evaluated the item, and journeys that never
have.

Useful future reporting view:

- `v_evaluation_group_status` or similar, deriving journey status per
  `evaluation_group_id`, including `basic_competent`, `fully_competent`,
  `sessions_to_competence`, and `days_to_competence`.

---

## 6. CouchDB Databases

| Logical name | Default DB | Env var |
| --- | --- | --- |
| sessions | `penplus_sessions` | `COUCHDB_DB_SESSIONS` |
| gaps | `penplus_gaps` | `COUCHDB_DB_GAPS` |
| users | `penplus_users` | `COUCHDB_DB_USERS` |
| districts | `penplus_districts` | `COUCHDB_DB_DISTRICTS` |
| facilities | `penplus_facilities` | `COUCHDB_DB_FACILITIES` |

`reporting/config/couchdb.php` defines this map. `SyncCouchDb.php` should use
that config map so `php artisan sync:couchdb --db=sessions` works as documented.

---

## 7. Current State

### monitoring/ — Core field workflow complete

Done:

- Nuxt 4 app scaffold with Nuxt UI, Pinia, VueUse, strict TypeScript
- `app/interfaces/` — all 6 interfaces (IItemScore, ISession, IGapEntry, IUserRef, IEvalItem, ITool)
- `app/data/evaluationItemData.ts` — all 11 tools + DC1–DC9 counselling
- MCP servers configured in `PPlusV2/.mcp.json` (nuxt, nuxt-ui)
- PouchDB 9 + `pouchdb-adapter-idb` installed
- `app/composables/useDb.ts` — PouchDB init with idb adapter, exports `sessionsDb` and `gapsDb`
- Pinia stores: `sessionStore`, `gapStore`, `userStore`, `districtStore`, `syncStore`
- `app/middleware/identity.global.ts` — redirects to `/setup` if no current user set
- `app/plugins/init.client.ts` — loads stores on app start
- Layouts: `default.vue`, `setup.vue`

Pages built:

- `setup.vue` — user identity setup (pick name/role before using the app)
- `mentees/index.vue` — mentee list with search
- `mentees/[id].vue` — mentee detail: tool selection, per-tool journey cards with competency status; guards closed journeys (blocks new session with toast)
- `sessions/new.vue` — session form: tool items + counselling scores, phase selector, notes
- `sessions/preview.vue` — session preview before save (reads counselling metadata from `counsellingTool`, not hardcoded)
- `sessions/journey.vue` — journey view: session list sorted by date, competency banner, full gap management (log gap via `GapForm` drawer, resolve via modal). A Sessions / Competency grid switch (`?view=grid`) shows `CompetencyGrid`: the paper tool's layout, competencies down the side, sessions across the top, each cell the session's latest score, plus Latest and Status columns from the competency rule; tapping a score shows its Mentee/Mentor meanings.
- `sessions/session.vue` — individual session detail: score summary, score distribution, item scores, counselling scores, session notes
- `sync.vue` — sync status page

Composables:

- `useCompetency.ts` — `getCompetencyStatus(sessions, tool)` pure function + `useCompetency(sessions, tool)` reactive composable returning `status`, `isBasicCompetent`, `isFullyCompetent`
- `useSessionCalculations.ts` — `calculateScoreCounts()`, `calculateAverage()`, `formatDate()`

Components:

- `session/`: `ScoreRow`, `ScoreChip`, `EchoActivityCounts`, `EchoActivitySummary`, `PhaseSelector`, `RoundSelector`, `ScoreDistribution`, `ItemScoresList`, `ScoreLegend`
- `journey/`: `SummaryCard`, `SessionCard`, `EchoActivityCard`, `CompetencyGrid`
- `gap/`: `GapCard`, `GapForm` (UDrawer bottom sheet)

Still needed:

- Capacitor — deferred, not a current priority
- Login / authentication (currently just a name/role picker in setup.vue)

### reporting/ — COMPLETE (admin panel fully operational)

Done:

- Laravel 12 + Filament 3 + Inertia 3 installed
- `penplus_reporting` MySQL database created
- All 14 migrations run: 10 tables + `v_sessions_numbered` + `v_session_averages` views
- All 10 Eloquent models: User, District, Facility, Tool, ToolCategory,
  EvaluationItem, EvaluationSession, SessionItemScore, GapEntry, SyncCheckpoint
- `ToolsAndItemsSeeder` written and run — tools, categories, and items seeded
- All 5 CouchDB databases created on the CouchDB server
- `sync:couchdb` command verified working end-to-end
- Scheduled sync every 5 minutes in `routes/console.php`
- Filament admin panel live at `/admin` with 5 resources:
  - `DistrictResource` — read-only list, facilities count (nav group: Reference Data)
  - `FacilityResource` — read-only list, filter by district
  - `UserResource` — clinician list, "Manage Access" edit for email/password
  - `EvaluationSessionResource` — table with tool/district/phase/date filters, view page with item scores infolist
  - `GapEntryResource` — table with tool/resolved filters, view modal infolist
- Admin user created via tinker (email: `admin@penplus.local`)
- User model implements `HasName` (`getFilamentName()`) for Filament panel display

Still needed in reporting/:

- Filament dashboard widgets (sessions count, scores by district/tool, etc.)
- `v_evaluation_group_status` view for competency closure metrics
  (`basic_competent`, `fully_competent`, `sessions_to_competence`, `days_to_competence`)
- Reporting pages / charts for time-to-competence analysis

---

## 8. Tool Summary

| Tool slug | Label | Items | Notes |
| --- | --- | --- | --- |
| `counselling` | General Counselling Competencies | DC1-DC9 (9) | Cross-cutting, every session |
| `diabetes` | Diabetes Mellitus | D1-D37 (37) | all basic |
| `cardiac` | Heart Diseases | C1-C36 (36) | C32-C36 advanced |
| `echo` | Echocardiogram | E1-E10 (9, no E9) | E7 advanced |
| `sickle_cell` | Sickle Cell Disease | S1-S45 (45) | S35-S37 advanced |
| `respiratory` | Respiratory Diseases | R1-R26 (26) | all basic |
| `hypertension` | Severe Hypertension | H1-H19 (19) | all basic |
| `ckd` | Chronic Kidney Disease & Nephrotic Syndrome | H1-H25 (25) | H15 advanced |
| `epilepsy` | Epilepsy | E1-E19 (18, no E14) | E5-E6 advanced |
| `palliative` | Palliative Care | P1-P13 (13) | P9 is second P8 in doc |
| `liver` | Chronic Liver Disease | L1-L15 (15) | L10 advanced |

Exports in `evaluationItemData.ts`:

- `counsellingTool`
- `evaluationTools`
- `allTools`
- `getToolBySlug(slug)`
- `getItemBySlug(slug)`
- `toolItemCounts`

---

## 9. Sync Command

Command:

```bash
php artisan sync:couchdb
php artisan sync:couchdb --db=sessions
php artisan sync:couchdb --reset
php artisan sync:couchdb --batch=500
```

Process:

1. Read `last_seq` from `sync_checkpoints`.
2. Call CouchDB `/{db}/_changes?feed=normal&include_docs=true&since=<seq>&limit=<batch>`.
3. Process each doc in a DB transaction.
4. Save the new `last_seq`.
5. Continue until `pending == 0`.

Processors:

- `processSession()` upserts `evaluation_sessions`, then delete/reinserts
  `session_item_scores`.
- `processGap()` upserts `gap_entries`.
- `processUser()` upserts `users`.
- `processDistrict()` upserts `districts`.
- `processFacility()` upserts `facilities`.
- `handleDeleted()` currently logs only.

---

## 10. Next Work Order

### Completed

- Steps 1–7: full monitoring field workflow
- Step 8: CouchDB live replication (`startSync` / `stopSync`) — implemented and tested end-to-end
- Storage cleanup: `syncStore.runCleanup()` — journey-aware purge, triggered from `sync.vue`

### Storage cleanup detail

`syncStore.runCleanup()` strategy:

1. **Compact both DBs** (`sessionsDb.compact()` + `gapsDb.compact()`) — removes MVCC revision history, always safe.
2. **Skip in-progress journeys** — any `evaluationGroupId` where `getCompetencyStatus` returns `'in_progress'` is left untouched.
3. **Purge old sessions from closed journeys** — for journeys that are `basic_competent` or `fully_competent`, remove sessions except the latest and any session holding an item's most recent score (competency depends on those), provided `syncStatus === 'synced'` **and** older than 30 days. Deletions are written as `_deleted` + `localPurge: true` tombstones, and the sessions push filter in `startSync()` skips them, so the server copy is never deleted.
4. **Reload the session store** if any documents were removed.

Exposed state: `cleanupRunning`, `lastCleanupAt`, `lastCleanupPurged`. Triggered manually from `sync.vue`.

### Next — reporting enhancements (current priority)

1. **`v_evaluation_group_status` MySQL view** — derives per-journey metrics:
   `basic_competent`, `fully_competent`, `sessions_to_competence`, `days_to_competence`,
   `first_session_date`, `competent_session_date`

2. **Filament dashboard widgets** — sessions count, avg score by district/tool, competency rates

3. **Competency progress charts** — item-level score trends over sessions

### Deferred

- Capacitor (Android packaging) — not a current priority

---

## 11. Known Gotchas

- Do not reintroduce mentor/autonomy scores.
- Do not store `sessionNumber`.
- Do not cap journeys at 5 sessions.
- Do not create one CouchDB document with nested session arrays.
- Use tool-namespaced item slugs.
- Keep counselling as cross-cutting DC1-DC9 (every tool except Echocardiogram).
- Use `evaluationGroupId` exactly as `${mentee.id}::${toolSlug}`.
- `sync_checkpoints.last_seq` must remain a string/VARCHAR because CouchDB
  sequences can be strings.
- `session_item_scores` delete/reinsert on session update is acceptable because
  there are no downstream foreign keys from that table.
- The old app is useful for workflow/reporting reference, not as a data model to
  copy wholesale.
- **USelect inside UModal**: `USelect` portals its dropdown to `<body>` by
  default (`portal: true`). The modal overlay sits above the teleported dropdown
  and blocks pointer events. Fix: add `:portal="false"` to `USelect` inside any
  `UModal`. Not needed inside `UDrawer` — the drawer doesn't create a blocking
  overlay layer.
- **USelect empty-string values**: Nuxt UI reserves `''` as the "clear
  selection" sentinel. Never add `{ value: '' }` items. For an optional select,
  use `ref<MyType | undefined>(undefined)` and set `placeholder="Not specified"`
  on the `USelect` instead of a blank item.
- **Icons must be bundled for offline use**: `nuxt.config.ts` disables the
  Iconify API (`provider: 'none'`, `fallbackToApi: false`). Write icon names as
  full literals (`'i-heroicons-calendar'`) so the build scanner finds them.
  Nuxt UI's internal Lucide icons are listed explicitly in `clientBundle.icons`.
  See `monitoring/TABLET_UI.md`.
- **Tablet sizing**: the root font is 112.5% at 768px+. Use rem-based Tailwind
  utilities, never `px` text sizes such as `text-[10px]`. Put text on score
  colours with `scoreTextColor()` from `evaluationItemData.ts`.
- **Only evaluators can be picked as the device identity**: roles are assigned
  in the reporting dashboard (`roles` table: admin, district_admin, evaluator).
  `CouchDbUserPushService` writes the role name into the CouchDB user doc as
  `role`. `userStore.evaluators` filters on `EVALUATOR_ROLES`, and `setup.vue`
  lists only those. Device edits keep the existing `role`, and
  `SyncCouchDb::processUser()` never clears `role_id` when a doc has no role.
  Run `php artisan couchdb:push-users` after assigning roles in bulk.
- **Back navigation is centralised**: the app header shows the Back button on
  every page except `/`. Do not add per-page back arrows. Register a new page's
  fallback parent in `getParentRoute()` in `composables/useBackNavigation.ts`.
- **Rounds choose their own competencies**: each `IRound` stores
  `selectedItemSlugs` / `selectedCounsellingSlugs`. A session's own
  `selectedItemSlugs` is the union of its rounds. Older sessions without
  per-round lists fall back to the session list or to what each round scored.
- **Advanced flags live in two places**: `monitoring/app/data/evaluationItemData.ts`
  is the source of truth. `reporting/database/data/evaluation_items.json` is
  generated from it for `ToolsAndItemsSeeder`; regenerate it whenever item data
  changes, then re-run the seeder, or the dashboard will judge competency on
  different advanced items than the app.
- **Gaps replace session notes** (client request, 2026-10-22) and are logged
  **per round**: the session form ends with "Gaps identified in <round>" for the
  active round. Drafts live on each `DraftRound.gaps` and are saved by
  `preview.vue` after the session, each with `sessionId`, `roundId` and 1-based
  `roundIndex` (synced to `gap_entries.session_id` / `round_index`). Deleting a
  round deletes its draft gaps. The session form also lists open gaps from earlier
  sessions. Session notes are no longer entered; notes on older sessions are
  shown read-only and preserved on edit.
- **Competency coverage** tracks what a journey has not covered, whoever the
  evaluator was, so a mentor can pick up a mentee from a colleague. Each tool
  item is `achieved` (latest ≥4), `pending` (latest <4), `never` or `only_na`.
  A pending item is *stale* once it has gone `STALE_AFTER_SESSIONS` (2) sessions
  without a score; achieved items are never flagged. Monitoring:
  `getJourneyCoverage()` in `composables/useCoverage.ts`, shown by
  `journey/CoverageCard.vue` on the journey page, by chips on the plan screen in
  `sessions/new.vue`, by a count on `mentees/[id].vue`, and by the home card
  "Competencies not yet evaluated" (Mine / All evaluators). Reporting: the
  `v_item_coverage` view (one row per journey × item) and
  `CompetencyCoverageController` (`/competency-coverage`, CSV export). Keep the
  two in step.
- **Demo data is the showcase set** (2026-10-23): `monitoring/scripts/seed-showcase-couchdb.ts`
  seeds 10 mentees and 15 journeys, each showing one feature (near-competent,
  Basic vs Fully Competent, irregular rounds, handed-over journey, coverage
  gaps, echo counts, struggling mentee, per-round gaps, just started). The
  presenter is `evaluator@penplus.local`; three colleague evaluators are demo
  users. Run `php artisan couchdb:push-users` before seeding. The old 100-mentee
  `seed-demo-couchdb.ts` is kept but no longer used.
- **Not assessable** (client request, 2026-10-26; see `NOT_ASSESSABLE.md`): a tool
  item can be marked not assessable at the mentee's facility with a reason code
  (`IItemScore.notAssessable`, score null). The latest entry wins: a later score
  lifts the mark, and N/A doesn't. Marked items are left out of Basic and Fully
  Competent, and out of coverage. Monitoring uses `getNotAssessableItems()` in
  `useCompetency.ts`. Reporting stores it in `session_item_scores.not_assessable_reason`
  and `not_assessable_note`, and applies the same rule in `v_evaluation_group_status`,
  `v_journey_progress`, `v_item_coverage` and `v_latest_item_scores`
  (migration `2026_10_26_000001`). The report is at `/not-assessable`. Reason labels
  live in three places; keep them in step.
- **Evaluators see every district in reporting** (they move between
  facilities). `ReportScopeService` and `canFilterDistrict()` exempt
  `isEvaluator()`; only district admins stay district-scoped.
- **Gaps still belong to the journey**: `evaluationGroupId` is the owner and gaps
  are resolved on `sessions/journey.vue`. `sessionId` only records where a gap
  was found, so `session.vue`, the review page and the form show it read-only
  (`GapCard readonly`). Gaps logged from the journey page have no `sessionId`.
