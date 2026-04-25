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

The recommended closure rule is:

- `basic_competent`: all non-advanced tool items have scores 4 or 5.
- `fully_competent`: all tool items, including advanced items, have scores 4 or
  5.

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
selected disease tool.

- Monitoring stores them in `ISession.counsellingScores`.
- MySQL stores them as normal `evaluation_items` rows under the `counselling`
  tool.
- There is no separate counselling table.

### Advanced items

`isAdvanced: true` marks grey/paper advanced competencies. These are not required
for `basic_competent`, but they should remain reportable.

Known items currently marked advanced in `evaluationItemData.ts`:

- Diabetes pregnancy: D35-D37
- Cardiac pregnancy: C32-C36
- Sickle cell inpatient/pregnancy: S34-S38 and S41-S45
- Epilepsy pregnancy: E17-E19
- CKD palliative: H15
- Liver procedures: L9, L13-L15

Verify grey shading with the clinical team before go-live.

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
- `sessions/journey.vue` — journey view: session list sorted by date, competency banner, full gap management (log gap via `GapForm` drawer, resolve via modal)
- `sessions/session.vue` — individual session detail: score summary, score distribution, item scores, counselling scores, session notes
- `sync.vue` — sync status page

Composables:

- `useCompetency.ts` — `getCompetencyStatus(sessions, tool)` pure function + `useCompetency(sessions, tool)` reactive composable returning `status`, `isBasicCompetent`, `isFullyCompetent`
- `useSessionCalculations.ts` — `calculateScoreCounts()`, `calculateAverage()`, `formatDate()`

Components:

- `session/`: `EvalItemRow`, `ItemCard`, `ScoreButtons`, `ScorePill`, `PhaseSelector`, `SaveBar`, `ProgressBar`, `ScoreDistribution`, `ItemScoresList`, `ScoreLegend`
- `journey/`: `SummaryCard`, `SessionCard`
- `gap/`: `GapCard`, `GapForm` (UDrawer bottom sheet)

Still needed:

- CouchDB live replication via PouchDB `.sync()` — `syncStore` has state scaffolding but actual replication is not wired
- Capacitor setup after core screens are validated on device
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
| `diabetes` | Diabetes Mellitus | D1-D37 (37) | D35-D37 advanced |
| `cardiac` | Heart Diseases | C1-C36 (36) | C32-C36 advanced |
| `echo` | Echocardiogram | E1-E10 (9, no E9) | all basic |
| `sickle_cell` | Sickle Cell Disease | S1-S45 (45) | S34-S38, S41-S45 advanced |
| `respiratory` | Respiratory Diseases | R1-R26 (26) | all basic |
| `hypertension` | Severe Hypertension | H1-H19 (19) | all basic |
| `ckd` | Chronic Kidney Disease & Nephrotic Syndrome | H1-H25 (25) | H15 advanced |
| `epilepsy` | Epilepsy | E1-E19 (18, no E14) | E17-E19 advanced |
| `palliative` | Palliative Care | P1-P13 (13) | P9 is second P8 in doc |
| `liver` | Chronic Liver Disease | L1-L15 (15) | L9, L13-L15 advanced |

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

Steps 1–7 of the original plan are largely complete. Remaining work in priority order:

### Step 8 — CouchDB live replication

`startSync()` and `stopSync()` in `syncStore.ts` are implemented and wire live PouchDB `.sync()` for all four databases. This is ready but has not been tested against a live CouchDB instance in PPlusV2.

### Storage cleanup

Implemented. `syncStore.runCleanup()` runs a journey-aware purge strategy:

1. **Compact both DBs** (`sessionsDb.compact()` + `gapsDb.compact()`) — removes MVCC revision history, always safe.
2. **Skip in-progress journeys** — any `evaluationGroupId` where `getCompetencyStatus` returns `'in_progress'` is left untouched.
3. **Purge old sessions from closed journeys** — for journeys that are `basic_competent` or `fully_competent`, remove all sessions except the latest, provided the session is `syncStatus === 'synced'` **and** older than 30 days (`createdAt` and `updatedAt` both before the cutoff).
4. **Reload the session store** if any documents were removed.

Exposed state: `cleanupRunning`, `lastCleanupAt`, `lastCleanupPurged`.

The cleanup is triggered manually from `sync.vue` ("Clean Up" button). It can also be called programmatically after a successful sync cycle.

### Step 9 — Capacitor (after replication validated)

```bash
pnpm add @capacitor/core @capacitor/cli
npx cap init "PenPlus" "com.solidarmed.penplus"
pnpm add @capacitor/android
npx cap add android
```

Use `pouchdb-adapter-idb` for web; switch to `pouchdb-adapter-cordova-sqlite` if needed on Android for better storage guarantees.

### Step 10 — reporting enhancements (lower priority)

- `v_evaluation_group_status` view: `basic_competent`, `fully_competent`,
  `sessions_to_competence`, `days_to_competence` per `evaluation_group_id`
- Filament dashboard widgets: sessions count, avg score by district/tool
- Competency progress charts

---

## 11. Known Gotchas

- Do not reintroduce mentor/autonomy scores.
- Do not store `sessionNumber`.
- Do not cap journeys at 5 sessions.
- Do not create one CouchDB document with nested session arrays.
- Use tool-namespaced item slugs.
- Keep counselling as cross-cutting DC1-DC9.
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
- **Gaps are journey-scoped, not session-scoped**: `IGapEntry` links to
  `evaluationGroupId`, not to a session `_id`. Gap management belongs on
  `sessions/journey.vue`. Do not add gap sections to `sessions/session.vue`.
