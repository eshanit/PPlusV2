# PenPlus Reporting — Implementation Plan

## 1. Architecture & Data Flow

```
Mentor's device (Nuxt / PouchDB)
        ↓  live sync (PouchDB ↔ CouchDB)
CouchDB (source of truth between apps)
        ↓  php artisan sync:couchdb (scheduled every 5 min)
MySQL reporting database
        ↓
Filament admin + Inertia report pages
```

Monitoring never writes to MySQL. Laravel polling CouchDB `_changes` is the
only bridge. Sync stores `last_seq` in `sync_checkpoints` so each poll is
incremental.

---

## 2. Critical Bugs to Fix Before Any Reporting Can Work

These three issues will cause data to fail silently or produce wrong results.
Fix them in order.

### 2.1 Session and gap IDs are too long for char(36)

PouchDB generates IDs like:

```
session::userId::toolSlug::1714000000000
gap::userId::toolSlug::1714000000001
```

These are up to ~100 characters. The current migrations use `char(36)` (UUID
size) — every sync will either truncate silently or throw an integrity error.

**Fix**: New migration to alter:
- `evaluation_sessions.id` → `varchar(200)`
- `gap_entries.id` → `varchar(200)`
- `session_item_scores.session_id` → `varchar(200)` (FK column)

### 2.2 Sync order — dimensions must come before facts

`SyncCouchDb::PROCESSORS` currently runs:
`sessions → gaps → users → districts → facilities`

`evaluation_sessions` has FK constraints on `mentee_id`, `evaluator_id`,
`facility_id`, `district_id` (all referencing tables synced AFTER sessions).
Every session sync run will fail with FK violations until those dimensions exist.

**Fix**: Reorder `PROCESSORS` constant:
`districts → facilities → users → sessions → gaps`

### 2.3 Competency logic mismatch between monitoring and reporting

The monitoring app uses **carry-forward** scoring: for each item in a journey,
it takes the most recent non-null score across *all* sessions in that
`evaluationGroupId`. A mentee is basic-competent when their latest score for
every non-advanced item is ≥ 4, regardless of which session that score came from.

The current `v_evaluation_group_status` checks if a *single session* has all
non-advanced items ≥ 4. This gives wrong results — a mentee who improved item A
in session 2 and item B in session 3 would never show as competent.

**Fix**: Add `v_latest_item_scores` view (carry-forward), then rebuild
`v_evaluation_group_status` on top of it. See Section 4.

---

## 3. What the Tool Measures (from PDF + code)

- Scores **1–5** per competency item. Goal is **4 or 5**.
- **Basic competence**: all non-advanced items (non-grey) ≥ 4.
- **Full competence**: all items including advanced ≥ 4.
- Advanced items are grey on the paper tool — reportable but not required.
- DC1–DC9 **counselling competencies** run in every session, all tools.
- Mentorship **phases**: Initial Intensive → Ongoing → Supervision.
- **GAP entries**: identified at the journey level (not per-session), with:
  domain, covered in mentorship (Y/N), covering later, timeline, supervision
  level after journey, and resolution note.
- Competency **domains** in gaps: knowledge, critical_reasoning, clinical_skills,
  communication, attitude.

---

## 4. New Analytic Views Needed

### 4.1 `v_latest_item_scores` — carry-forward per journey

For each `(evaluation_group_id, item_id)`, return the most recent non-null
`mentee_score` across all sessions in that group. This mirrors the monitoring
app's `getLatestScore()`.

```sql
CREATE OR REPLACE VIEW v_latest_item_scores AS
WITH ranked AS (
    SELECT
        es.evaluation_group_id,
        es.mentee_id,
        es.tool_id,
        es.district_id,
        es.facility_id,
        sis.item_id,
        sis.mentee_score,
        es.eval_date,
        ROW_NUMBER() OVER (
            PARTITION BY es.evaluation_group_id, sis.item_id
            ORDER BY es.eval_date DESC, es.created_at DESC
        ) AS rn
    FROM session_item_scores sis
    JOIN evaluation_sessions es ON es.id = sis.session_id
    WHERE sis.mentee_score IS NOT NULL
)
SELECT
    evaluation_group_id,
    mentee_id,
    tool_id,
    district_id,
    facility_id,
    item_id,
    mentee_score,
    eval_date AS score_date
FROM ranked
WHERE rn = 1;
```

### 4.2 Rebuild `v_evaluation_group_status` on carry-forward

The current view evaluates competency per session. Rebuild it to:
1. Pull the latest item score per item from `v_latest_item_scores`.
2. Count how many non-advanced items are ≥ 4 (basic competence test).
3. Find the earliest session where carry-forward competency is first reached.

This is a bigger SQL rewrite. The correct approach uses a windowed aggregation
over sorted sessions, rechecking carry-forward state after each one. See
implementation notes in Section 7.

### 4.3 `v_journey_summary` — one row per mentee+tool journey

Joining `v_evaluation_group_status` with `v_session_averages` and gap counts
for efficient report queries:

```sql
CREATE OR REPLACE VIEW v_journey_summary AS
SELECT
    vgs.*,
    t.label               AS tool_label,
    t.slug                AS tool_slug,
    d.name                AS district_name,
    f.name                AS facility_name,
    u.firstname           AS mentee_firstname,
    u.lastname            AS mentee_lastname,
    -- Latest session average (from v_session_averages)
    latest_avg.avg_mentee_score   AS latest_avg_score,
    latest_avg.scored_items       AS latest_scored_items,
    -- Gap counts
    COALESCE(gap_counts.open_gaps, 0)     AS open_gaps,
    COALESCE(gap_counts.resolved_gaps, 0) AS resolved_gaps
FROM v_evaluation_group_status vgs
JOIN tools t ON t.id = vgs.tool_id
LEFT JOIN districts d ON d.id = vgs.district_id
LEFT JOIN facilities f ON f.id = vgs.facility_id
JOIN users u ON u.id = vgs.mentee_id
LEFT JOIN v_session_averages latest_avg
    ON latest_avg.session_id = (
        SELECT id FROM evaluation_sessions
        WHERE evaluation_group_id = vgs.evaluation_group_id
        ORDER BY eval_date DESC, created_at DESC
        LIMIT 1
    )
LEFT JOIN (
    SELECT
        evaluation_group_id,
        SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) AS open_gaps,
        SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved_gaps
    FROM gap_entries
    GROUP BY evaluation_group_id
) gap_counts ON gap_counts.evaluation_group_id = vgs.evaluation_group_id;
```

---

## 5. Reporting Feature Plan

Reports are split into: **Snapshot** (client priority — latest state) and
**Trend** (historical analysis).

### 5.1 Dashboard Widgets (Filament StatsOverview)

Four headline stats — always visible on the admin dashboard:

| Widget | Value | Source |
|--------|-------|--------|
| Total Mentees | COUNT(DISTINCT mentee_id) | evaluation_sessions |
| Active Journeys | journeys where status = 'in_progress' | v_evaluation_group_status |
| Competency Rate | % journeys with basic_competent = 1 | v_evaluation_group_status |
| Open Gaps | SUM(open_gaps) | gap_entries |

Two chart widgets:

- **Sessions by Tool** — bar chart, count of sessions grouped by tool.label
- **Competency Rate by District** — bar chart, % basic_competent per district

### 5.2 Snapshot Reports

**A. Journey Status Table** (Filament page or resource)

One row per mentee+tool journey. Filterable by tool, district, facility, status.

| Column | Source |
|--------|--------|
| Mentee | users.firstname + lastname |
| Tool | tools.label |
| Facility | facilities.name |
| District | districts.name |
| Sessions | total_sessions |
| Latest Score | latest_avg_score |
| Status | in_progress / basic_competent / fully_competent |
| Last Session | latest_session_date |
| Open Gaps | open_gaps |

**B. Needs Attention List**

Mentees where:
- Status = `in_progress` AND
- `latest_session_date < NOW() - 30 days`

OR:
- Latest session has items still below 4 after 3+ sessions

**C. Low-Score Watchlist** (item-level, latest session)

Items with the lowest average carry-forward score across active journeys.
Shows which competencies are consistently weak across the cohort.

| Column | Source |
|--------|--------|
| Item | evaluation_items.number + title |
| Tool | tools.label |
| Avg Score | AVG(mentee_score) from v_latest_item_scores |
| % At Goal | % of journeys with score ≥ 4 |
| Journeys Below 4 | count of journeys where latest score < 4 |

Filterable by tool, district. Sort by avg score ascending.

**D. Gap Overview**

Current open gaps grouped by domain and tool:

| Column | Source |
|--------|--------|
| Domain | gap_entries.domains (JSON) |
| Tool | tools.label |
| Open | count where resolved_at IS NULL |
| Resolved | count where resolved_at IS NOT NULL |
| Avg Days to Resolve | AVG(DATEDIFF(resolved_at, identified_at)) |
| Supervision Level | gap_entries.supervision_level |

### 5.3 Trend Reports

**E. Score Trajectory** (per mentee+tool journey)

For a selected mentee + tool: line chart of average session scores from session
1 to latest. Uses `v_sessions_numbered` + `v_session_averages`.

Also shows individual item score trends for selected items.

**F. Time to Competence Distribution**

Bar chart of `days_to_basic_competence` grouped into ranges:
`<30 days / 30–60 / 60–90 / 90–180 / 180+`

Segmented by tool. Answers: "how long does it typically take to reach
competency in each disease area?"

**G. Cohort Progress Over Sessions**

Line chart: for each session number (1, 2, 3, ...), what is the average score
across ALL journeys for that session number? Shows whether the cohort as a whole
is improving.

Uses `v_sessions_numbered` joined to `v_session_averages`.

**H. Evaluator Activity**

| Column | Source |
|--------|--------|
| Evaluator | users.full_name |
| Sessions This Month | count |
| Mentees Reached | count distinct mentee_id |
| Tools Used | count distinct tool_id |
| Avg Session Score | avg |

---

## 6. Implementation Sequence

### Step 1 — Schema Fixes (required before anything works)

1. New migration: alter `evaluation_sessions.id`, `gap_entries.id`,
   `session_item_scores.session_id` to `varchar(200)`.
2. Fix `SyncCouchDb::PROCESSORS` order: districts → facilities → users →
   sessions → gaps.
3. Run `php artisan sync:couchdb --reset` after fixes to re-import cleanly.

### Step 2 — New Views

1. Create `v_latest_item_scores` (carry-forward).
2. Rebuild `v_evaluation_group_status` using carry-forward logic.
3. Create `v_journey_summary`.

### Step 3 — Dashboard Widgets

1. `SessionsStatsWidget` — StatsOverview with 4 headline metrics.
2. `SessionsByToolWidget` — ChartWidget (bar, grouped by tool).
3. `CompetencyByDistrictWidget` — ChartWidget (bar, % competent per district).

### Step 4 — Snapshot Report Pages (Filament custom pages)

1. Journey Status page (filterable table from `v_journey_summary`).
2. Low-Score Watchlist page (item-level from `v_latest_item_scores`).
3. Gap Overview page (gap_entries aggregated).

### Step 5 — Needs Attention + Trend Pages

1. Needs Attention list (overdue in-progress journeys).
2. Score Trajectory (per-journey drill-down with chart).
3. Time to Competence distribution chart.
4. Evaluator Activity table.

### Step 6 — Inertia Report Pages (Optional, for richer UI)

If Filament pages feel too constrained, move the chart-heavy reports to
Inertia + Vue components. `ReportingDashboardController.php` is already wired.

---

## 7. Carry-Forward Competency SQL — Implementation Notes

The correct approach for `v_evaluation_group_status` rebuild:

```sql
-- Step 1: for each (group, item), find the latest non-null score
-- → v_latest_item_scores (already defined in Section 4.1)

-- Step 2: for each group, count items meeting the threshold
WITH group_item_status AS (
    SELECT
        lis.evaluation_group_id,
        lis.mentee_id,
        lis.tool_id,
        lis.district_id,
        lis.facility_id,
        COUNT(*) AS total_scored_items,
        SUM(CASE WHEN ei.is_advanced = 0 AND lis.mentee_score >= 4 THEN 1 ELSE 0 END)
            AS basic_competent_items,
        SUM(CASE WHEN ei.is_advanced = 0 THEN 1 ELSE 0 END)
            AS basic_required_items,
        SUM(CASE WHEN lis.mentee_score >= 4 THEN 1 ELSE 0 END)
            AS fully_competent_items,
        COUNT(*) AS total_items_with_scores
    FROM v_latest_item_scores lis
    JOIN evaluation_items ei ON ei.id = lis.item_id
    JOIN tools t ON t.id = ei.tool_id AND t.slug != 'counselling'
    GROUP BY
        lis.evaluation_group_id, lis.mentee_id, lis.tool_id,
        lis.district_id, lis.facility_id
),
tool_item_counts AS (
    SELECT tool_id,
        COUNT(*) AS total_items,
        SUM(CASE WHEN is_advanced = 0 THEN 1 ELSE 0 END) AS basic_items
    FROM evaluation_items
    JOIN tools t ON t.id = tool_id AND t.slug != 'counselling'
    GROUP BY tool_id
)
SELECT
    gis.*,
    tic.total_items,
    tic.basic_items,
    (gis.basic_competent_items = tic.basic_items) AS basic_competent,
    (gis.fully_competent_items = tic.total_items)  AS fully_competent
FROM group_item_status gis
JOIN tool_item_counts tic ON tic.tool_id = gis.tool_id;
```

The harder part is computing *when* competency was first reached (for
`days_to_basic_competence` / `sessions_to_basic_competence`). This requires
replaying sessions in order and re-checking the carry-forward state after each
one — a recursive CTE or application-layer computation. This can be deferred
until after the snapshot reports are working.

---

## 8. Open Questions for the Client

1. **Competency definition**: The monitoring app uses "all non-advanced items ≥
   4". The paper tool mentions a 70% phase threshold. Which is the authoritative
   rule for reporting?

2. **Overdue threshold**: How many days without a session makes a journey
   "overdue" for the Needs Attention list? (Proposed: 30 days.)

3. **Advanced items in reports**: Should the report surface advanced item scores
   separately, or only show them in the full-competency rate?

4. **Gap resolution workflow**: Is resolution done in the monitoring app (it is),
   or should the reporting admin be able to mark gaps resolved? (Not recommended
   — keep write access in monitoring.)

5. **Chart library**: Filament's built-in ChartWidget uses Chart.js. For the
   Inertia pages, confirm whether to use Chart.js, ApexCharts, or another.
