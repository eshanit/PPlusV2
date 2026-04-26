I'm in Planning mode. Let me research the materials and existing setup to build a comprehensive plan.   

Research reporting migrations and sync



Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/doc1.md#1-1), lines 1 to 200

Fetched [resource](file:///c:/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/PEN-Plus Mentorship Tool. 2.0_April_ 2026.pdf)

Let me gather the rest of the context about migrations and sync implementation:   

Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/doc1.md#200-200), lines 200 to 414

Searched for files matching `**/reporting/database/migrations/**`, 17 matches

Searched for files matching `**/reporting/app/Console/Commands/SyncCouchDb.php`, 1 match

Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/reporting/app/Console/Commands/SyncCouchDb.php#1-1), lines 1 to 150

Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/reporting/database/migrations/2026_04_24_000007_create_evaluation_sessions_table.php)

Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/reporting/database/migrations/2026_04_25_200534_fix_session_and_gap_id_column_lengths.php)

Read [](file:///c%3A/Users/Admin/Documents/Projects/Solidarmed/NCD/PenPlus/PPlusV2/reporting/database/migrations/2026_04_25_201709_add_analytic_views.php)

Read memory

Created memory file [](file:///c%3A/Users/Admin/AppData/Roaming/Code/User/workspaceStorage/9292176c6e159791918c95a64febb847/GitHub.copilot-chat/memory-tool/memories/MTAyNTk4NTctOTZmMi00ZjJmLTk3OGMtN2U1MDRiOWJlNjdk/plan.md)

Now I'll ask the clarifying questions needed to finalize your plan: 

Analyzing your answers...

I need one critical clarification—your choice of **per-session competency** is significantly different from the monitoring app's carry-forward logic. Let me confirm this is intentional: 

Analyzing your answers...

Perfect. Now let me update the plan with these decisions and present it: 

Updated memory file [](file:///c%3A/Users/Admin/AppData/Roaming/Code/User/workspaceStorage/9292176c6e159791918c95a64febb847/GitHub.copilot-chat/memory-tool/memories/MTAyNTk4NTctOTZmMi00ZjJmLTk3OGMtN2U1MDRiOWJlNjdk/plan.md)

---

## Plan: PenPlus Reporting & Analysis Implementation

**TL;DR:** Build a dashboard-first reporting module with carry-forward analysis for journey snapshots (4 metrics + 2 charts), then drill-down to competency reports using per-session logic. Fix schema IDs first, create 3 analytic views, integrate ApexCharts for rich visuals. Estimated 6–8 weeks for full scope.

---

### **Steps**

#### **Phase 1: Data Repair** *(prerequisite — must complete first)*
1. Apply existing migration: `alter evaluation_sessions.id, gap_entries.id, session_item_scores.session_id to varchar(200)`
2. Run `php artisan sync:couchdb --reset` (re-import all CouchDB data cleanly after schema fix)
3. Verify sync checkpoint is clean; run one manual cycle to confirm

**Relevant files**  
- 2026_04_25_200534_fix_session_and_gap_id_column_lengths.php — already written, ready to migrate
- SyncCouchDb.php — PROCESSORS order is correct (no changes needed)

**Verification**  
- `php artisan migrate` runs without errors
- `php artisan sync:couchdb --batch=1000` completes successfully, pulls all sessions/gaps/users  
- `SELECT COUNT(*) FROM evaluation_sessions` returns non-zero count

---

#### **Phase 2: Analytics Foundation** *(3 MySQL views — core reporting logic)*

2.1 **Create `v_latest_item_scores` view** — *carry-forward per journey+item*  
Finds the most recent non-null score for each item in each journey. Used for:
- Watchlist (item-level analysis)
- Journey summary statistics  

2.2 **Create `v_evaluation_group_status` view** — *competency classification (per-session logic)*  
For each journey: 
- Find sessions where ALL non-advanced items ≥ 4 (per-session check)
- Flag `basic_competent` = true if such a session exists
- Find sessions where ALL items including advanced ≥ 4 → `fully_competent`
- Compute `sessions_to_basic_competence` (which session # first met threshold)
- Compute `days_to_basic_competence` (eval_date of that session)

**Key difference**: This uses **per-session** logic (stricter), not carry-forward. Dual systems:
- Monitoring app: carry-forward (mentee sees progress)
- Reporting: per-session (admin sees verification rigor)

2.3 **Create `v_journey_summary` view** — *one row per journey, all summary stats*  
Join `v_evaluation_group_status` with tools, districts, facilities, users, gap counts, latest session average.

**Relevant files**  
- 2026_04_25_201709_add_analytic_views.php *(currently placeholder; implement all 3 views here)*

**Verification**  
- `SELECT COUNT(*) FROM v_evaluation_group_status WHERE basic_competent = 1` returns reasonable % (5–40% typical)
- `SELECT * FROM v_journey_summary LIMIT 1` returns all expected columns with non-null summaries

---

#### **Phase 3: Dashboard & Headlines** *(Filament + ApexCharts widgets)*  

3.1 **Wire 4 headline metrics** (Filament `StatsOverview`)
- Total Mentees: `COUNT(DISTINCT mentee_id) FROM evaluation_sessions`
- Active Journeys: `COUNT(*) FROM v_evaluation_group_status WHERE competency_status = 'in_progress'`
- Competency Rate: `SUM(basic_competent) / COUNT(*) * 100 FROM v_evaluation_group_status`
- Open Gaps: `COUNT(*) FROM gap_entries WHERE resolved_at IS NULL`

3.2 **Add 2 chart widgets** (ApexCharts, Inertia pages)
- **Sessions by Tool**: bar chart, session count grouped by `tools.label`
- **Competency by District**: bar chart, `basic_competent %` per district

3.3 **Create Filament dashboard page** that loads all widgets

**Relevant files**  
- Create: `reporting/app/Filament/Pages/Dashboard.php` (extend `BaseDashboard`, register widgets)
- Create: `reporting/app/Filament/Widgets/SessionsStatsWidget.php` (StatsOverview with 4 metrics)
- Create: `reporting/resources/js/Pages/DashboardChart.jsx` (Inertia wrapper for ApexCharts)
- Modify: web.php (add route for chart endpoints)

**Verification**  
- Navigate to `/admin` → dashboard loads, all 4 numbers visible and non-zero
- Chart widgets render without JS console errors
- Clicking chart shows axis labels and data point tooltips (ApexCharts)

---

#### **Phase 4: Snapshot Report Pages** *(Filament custom resources + Inertia*  

*Depends on Phase 3* *(dashboard provides context)*

4.1 **Journey Status page** (filterable table)
- Source: `v_journey_summary`
- Columns: Mentee, Tool, Facility, District, Sessions, Latest Score, Status, Last Session Date, Open Gaps
- Filters: Tool, District, Facility, Status (in_progress / basic_competent / fully_competent)
- Action: click row to drill into Score Trajectory (Phase 5)

4.2 **Low-Score Watchlist page** (item-level analysis)
- Source: `v_latest_item_scores` + `evaluation_items` + aggregation
- Columns: Item #, Item Title, Tool, Average Carry-Forward Score, % at Goal (≥4), Journeys Below 4
- Filters: Tool, District
- Sort: ascending avg score (weakest competencies first)

4.3 **Gap Overview page** (aggregated gap analytics)
- Source: `gap_entries`
- Columns: Domain, Tool, Open Count, Resolved Count, Avg Days to Resolve, Supervision Level
- Filters: Tool, Domain, Resolution Status
- Visualization: stacked bar (open vs resolved per domain)

**Relevant files**  
- Create: `reporting/app/Filament/Pages/Reports/JourneyStatus.php` (custom page with DataTable)
- Create: `reporting/app/Filament/Pages/Reports/LowScoreWatchlist.php`
- Create: `reporting/app/Filament/Pages/Reports/GapOverview.php`
- Modify: AdminPanelProvider.php (register pages in nav)

**Verification**  
- Each page loads in `/admin` sidebar
- Filters work; results update on filter change
- Table exports to CSV work (native Filament feature)

---

#### **Phase 5: Trend & Drill-Down Reports** *(Inertia pages + ApexCharts)*

*Depends on Phases 3–4*

5.1 **Needs Attention list** (overdue in-progress journeys)
- Source: `v_journey_summary WHERE competency_status = 'in_progress' AND DATEDIFF(NOW(), latest_session_date) > 30`
- Columns: Mentee, Tool, Days Since Last Session, Latest Score, Open Gaps
- Action: click to trigger follow-up email or SMS prompt (optional phase 6)

5.2 **Score Trajectory** (per-journey drill-down)
- Source: `v_sessions_numbered` joined to `v_session_averages`
- Chart: line graph showing average session score progression (session 1 → latest)
- Optional: overlay individual item scores on same chart
- Filterable: by item (show only specific items)

5.3 **Time to Competence distribution**
- Source: `v_evaluation_group_status.days_to_basic_competence`
- Chart: histogram grouped into bins (0–30, 30–60, 60–90, 90–180, 180+)
- Segmented: by tool (stacked bars)
- Insight: "Diabetes averages 45 days, Cardiac averages 72 days"

5.4 **Cohort Progress Over Sessions**
- Source: `v_sessions_numbered` + `v_session_averages`
- Chart: line graph, average score for session #1, #2, #3, etc. across ALL mentees
- Insight: "Session 2 average is 2.1, session 3 is 2.9 → improving cohort"

5.5 **Evaluator Activity** (monthly snapshot)
- Source: `evaluation_sessions` grouped by `evaluator_id`
- Columns: Evaluator Name, Sessions This Month, Unique Mentees, Tools Used, Avg Score
- Filters: Date range, evaluator

**Relevant files**  
- Create: `reporting/app/Http/Controllers/Reports/TrendReportController.php` (data providers)
- Create: `reporting/resources/js/Pages/Reports/ScoreTrajectory.jsx` (Inertia component)
- Create: `reporting/resources/js/Pages/Reports/TimeToCompetence.jsx`
- Create: `reporting/resources/js/Pages/Reports/CohortProgress.jsx`
- Create: `reporting/resources/js/Pages/Reports/EvaluatorActivity.jsx`
- Update: web.php (new routes for Inertia pages)

**Verification**  
- Each report page loads and renders ApexCharts without console errors
- Date filters and segmentation work; charts update dynamically
- Exported trend data (CSV) matches on-screen values

---

#### **Phase 6: Refinements & Polish** *(optional; later phase)*

6.1 **Scheduled exports** (daily/weekly CSV snapshots)
6.2 **User role-based access** (district/facility admins see only their data)
6.3 **Gap resolution workflow** (if client decides admin needs write access later)
6.4 **Performance optimization** (materialized views, query caching if needed at scale)

---

### **Relevant Files**

**Schema & Sync**
- 2026_04_25_200534_fix_session_and_gap_id_column_lengths.php — ID column fixes (ready)
- SyncCouchDb.php — Sync order is correct, no changes needed
- 2026_04_25_201709_add_analytic_views.php — Implement 3 views here

**Existing Filament Resources** (reference for consistency)
- DistrictResource.php
- EvaluationSessionResource.php

**Existing Inertia Setup** (reference for controllers)
- web.php — already has Inertia setup
- inertia.php — props configuration

---

### **Verification**

**Phase 1 (Data Repair)**
- ✓ Migration applies without errors
- ✓ Sync command pulls data cleanly
- ✓ evaluation_sessions.id column is varchar(200), accepts long PouchDB IDs

**Phase 2 (Views)**
- ✓ `v_latest_item_scores` returns one row per unique (journey, item) with latest non-null score
- ✓ `v_evaluation_group_status` shows 5–40% basic_competent rate (realistic for ongoing programs)
- ✓ `v_journey_summary` has zero NULL foreign key columns for active journeys

**Phase 3 (Dashboard)**
- ✓ Filament dashboard loads at `/admin`
- ✓ All 4 headline numbers visible and non-zero
- ✓ Chart widgets render with ApexCharts tooltip on hover

**Phase 4 (Snapshot Reports)**
- ✓ Journey Status page shows >0 rows with correct tool/facility/status filters
- ✓ Low-Score Watchlist identifies items with avg carry-forward <3.0
- ✓ Gap Overview aggregates by domain correctly

**Phase 5 (Trends)**
- ✓ Score Trajectory line chart shows monotonically non-decreasing or realistic progression
- ✓ Time to Competence histogram groups are non-empty
- ✓ Evaluator Activity table shows accurate session counts by person

---

### **Decisions**

- **Competency logic**: Reporting uses per-session (stricter) vs monitoring's carry-forward. Dual systems intentional.
- **Priority order**: Dashboard → Snapshot Reports → Trends (quick wins first, then detail)
- **Charts**: ApexCharts for Inertia (interactive, mobile-friendly); Chart.js in Filament (simpler integration)
- **Gap workflow**: Read-only in reporting; resolution in monitoring only
- **Advanced items**: Tracked separately — both basic% and full% visible on dashboard

---

### **Further Considerations**

1. **Large cohorts**: If >1000 mentees, Phase 2 views may need optimization (indexed derived tables or materialized refresh). Plan for query profiling after Phase 3.

2. **Historical data handoff**: If migrating from PenPlusUltimate, need reconciliation script to backfill `evaluation_sessions` and `session_item_scores` from old data. Deferred until Phase 6.

3. **Counselling scores**: Currently synced as `evaluation_items` under the `counselling` tool (not separate table). Watchlist and Status reports should filter to show them, but competency status only uses disease tool items. Confirm this scope with clinical team before Phase 2.

---
