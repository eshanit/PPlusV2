# Score Distribution Analysis Chart - Implementation Guide

## 1. Legacy Component Analysis

### Source
- **Component**: `aggregateToolScoreCounts.vue` (PenPlusUltimate)
- **Parent**: `pages/ReportPlatform/reporting/tools/index.vue`
- **Purpose**: Displays heatmap-style table of competency score distribution across all disease tools

### What It Does

The component aggregates evaluation data by:
1. **Collecting score frequencies** for each tool across all evaluations
2. **Binning scores** into counts: 0, 1, 2, 3, 4, 5
3. **Visual heatmap** using intensity-based color coding based on count values:
   - Gray: No data (count = 0)
   - Light gray: 1-5 occurrences
   - Green gradient: 6-100+ occurrences (deepening with count)
4. **Legend** showing color-to-count mapping for interpretation

### Input Data Structure
```typescript
// IFinalEvaluation (from legacy)
toolEvals: IFinalEvaluation[]
```

The component uses `useNumResponsesPerTool(toolEvals)` to aggregate per-tool score distributions.

### Key Logic Flow
```typescript
aggregateCounts: computed(() => {
  // For each tool: sum scores 0-5 across all items
  // Result: { toolName: { zeros: N, ones: N, ..., fives: N } }
})

countColorClass(count: number): string {
  // Maps count ranges to CSS classes
  // 0 → gray-50
  // 1-5 → gray-100
  // 6-10 → green-100
  // ... deepens to green-900 for 100+
}
```

---

## 2. PPlusV2 Alignment & Enhancements

### A. Current State in PPlusV2

**Reporting dashboard already has:**
- 6 Filament dashboard widgets (active journeys, competency rates, sessions by tool, etc.)
- MySQL tables with aggregated score data available
- Views: `v_sessions_numbered`, `v_session_averages`
- Base data: `session_item_scores` table (menteeScore: 1-5 or null)

**Missing:**
- Score distribution visualization (this chart)
- Item-level heatmap analysis

### B. Enhancement Opportunities

| Enhancement | Benefit | Complexity |
|-------------|---------|-----------|
| **Filter by district/facility** | Regional score distribution patterns | Low |
| **Filter by date range** | Trend analysis: score improvement over time | Low |
| **Toggle: items vs aggregated view** | Detailed item-level gaps + summary view | Medium |
| **Export to CSV** | Data archiving & external reporting | Low |
| **Interactive tooltips** | Show sample size, completion status | Low |
| **Stacked bar chart alternative** | Alternative visualization (esp. mobile) | Medium |
| **Score trajectory chart** | Show avg score per tool over time | Medium |

### C. Recommended Improvements for PPlusV2

1. **Direct from MySQL** (not legacy)
   - Query `session_item_scores` → `evaluation_items` → `tools` for live data
   - Filter by `syncStatus='synced'` to avoid pending/failed sessions

2. **Respect null scores**
   - The legacy component treats all evaluated scores (0-5), but `null` = not assessed
   - PPlusV2 should: count nulls separately or exclude them, making this explicit

3. **Tool-namespaced display**
   - Show `{{ tool.label }}` (e.g., "Diabetes Mellitus") not slug
   - Include item count: "Diabetes (37 items)"

4. **Mobile-responsive table**
   - Horizontal scroll on mobile (current legacy already does this)
   - Consider collapsible score columns for narrow screens

5. **Contextual metadata**
   - Show total evaluations, total sessions underneath heading
   - Date range of data displayed

---

## 3. Where This Fits in PPlusV2

### A. Recommended Placement

| Context | Placement | Rationale |
|---------|-----------|-----------|
| **Admin dashboard** | Not here — too detailed for overview | Clutters main dashboard |
| **New "Score Analysis" Filament page** | ✅ **Primary** | Dedicated analysis page, accessible from admin nav |
| **Reporting drill-down** | ✅ **Secondary** | Link from `EvaluationSessionResource` → view all scores distribution |
| **PDF export workflow** | Maybe | Future: export score heatmap in competency reports |

**Recommended Nav Structure:**
```
Admin Dashboard
├── Core Metrics (existing widgets)
├── Data Management
│   ├── Districts
│   ├── Facilities
│   ├── Users
├── Evaluations
│   ├── Sessions (resource, already exists)
│   ├── Gaps (resource, already exists)
│   └── Score Analysis ← NEW PAGE
└── Reports (future)
```

### B. Implementation Path

#### Option 1: Filament Widget (Recommended)
- Create a new `Filament\Widgets\CompetencyScoreDistributionWidget`
- Add to dashboard or a dedicated page
- Pros: Integrated, follows existing pattern, reusable
- Cons: Less flexible than standalone page
- **Effort**: 2–3 hours

#### Option 2: Standalone Filament Page
- Create `EvaluationScoreAnalysisPage`
- Add to admin navigation
- Pros: Full flexibility, large screen real estate, can add filters/exports
- Cons: New pattern (most of PPlusV2 is resources/widgets)
- **Effort**: 3–4 hours

#### Option 3: Inertia Page (Future)
- Could eventually be an Inertia SPA page in `monitoring` app for field teams
- Lower priority for now
- **Effort**: Deferred

---

## 4. Technical Implementation Details

### Data Query (Laravel)

```sql
-- Aggregate score counts per tool
SELECT
  t.label as tool_name,
  SUM(CASE WHEN sis.mentee_score IS NULL THEN 1 ELSE 0 END) as nulls,
  SUM(CASE WHEN sis.mentee_score = 1 THEN 1 ELSE 0 END) as ones,
  SUM(CASE WHEN sis.mentee_score = 2 THEN 1 ELSE 0 END) as twos,
  -- ... continue for 3, 4, 5
  COUNT(*) as total
FROM session_item_scores sis
JOIN evaluation_items ei ON sis.evaluation_item_id = ei.id
JOIN tools t ON ei.tool_id = t.id
WHERE sis.evaluation_session_id IN (
  SELECT es.id FROM evaluation_sessions es
  WHERE es.sync_status = 'synced'
  -- Optional filters
  -- AND es.eval_date >= ?
  -- AND es.facility_id IN (?)
)
GROUP BY t.id, t.label
ORDER BY t.sort_order;
```

### Service Class (Recommendation)

```php
// app/Services/ScoreDistributionService.php
class ScoreDistributionService
{
    public function getAggregateScoreCounts(
        ?array $districtIds = null,
        ?array $toolIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        // Build aggregation query
        // Return: { toolLabel: { nulls, ones, twos, threes, fours, fives, total } }
    }

    public function getItemLevelScoreCounts(...): array {
        // Return per-item distribution (optional drill-down)
    }
}
```

### Filament Widget Structure

```php
// app/Filament/Widgets/CompetencyScoreDistributionWidget.php
class CompetencyScoreDistributionWidget extends Widget
{
    protected static string $view = 'filament.widgets.competency-score-distribution-widget';

    public function getData(): array
    {
        return app(ScoreDistributionService::class)->getAggregateScoreCounts();
    }
}
```

### Blade View (Filament)

```blade
<!-- Score heatmap table similar to legacy -->
<div class="overflow-x-auto rounded-lg border border-gray-200">
  <table class="min-w-full">
    <!-- Header: Tool, Null, 1, 2, 3, 4, 5, Total -->
    <!-- Body: rows per tool with color-coded counts -->
  </table>
</div>
```

### Enhancements to Add

1. **Filters in page header** (if standalone page):
   ```php
   #[On('filter')]
   public function filter($districtId, $dateRange) {
       // Re-fetch data
   }
   ```

2. **Export button**:
   ```php
   public function export() {
       // Generate CSV from raw query
   }
   ```

3. **Tooltip data**:
   ```
   Hover over count → shows sample sessions, mentees, recent dates
   ```

---

## 5. Component Skeleton (Blade)

```blade
<x-filament::card>
    <div class="space-y-6">
        <!-- Header with metadata -->
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-xl font-semibold">Score Distribution</h2>
                <p class="text-sm text-gray-600">
                    {{ $data['totalSessions'] }} sessions across {{ $data['totalTools'] }} tools
                </p>
            </div>
            <!-- Filters / Export buttons here -->
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Tool</th>
                        <th class="px-4 py-2 text-center">N/A</th>
                        <th class="px-4 py-2 text-center">1</th>
                        <th class="px-4 py-2 text-center">2</th>
                        <th class="px-4 py-2 text-center">3</th>
                        <th class="px-4 py-2 text-center">4</th>
                        <th class="px-4 py-2 text-center">5</th>
                        <th class="px-4 py-2 text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['tools'] as $tool => $counts)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $tool }}</td>
                        @foreach(['nulls' => $counts['nulls'], 'ones' => $counts['ones'], ...] as $score => $count)
                        <td class="px-4 py-3 text-center {{ $this->countColorClass($count) }}">
                            {{ $count }}
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="grid grid-cols-3 gap-2 text-xs">
            <div><span class="inline-block w-3 h-3 bg-gray-100 rounded"></span> 1-5</div>
            <div><span class="inline-block w-3 h-3 bg-green-100 rounded"></span> 6-10</div>
            <!-- etc -->
        </div>
    </div>
</x-filament::card>
```

---

## 6. Color Intensity Mapping (Tailwind)

```php
protected function countColorClass(int $count): string
{
    return match (true) {
        $count === 0 => 'bg-gray-50 text-gray-400',
        $count <= 5 => 'bg-gray-100 text-gray-700',
        $count <= 10 => 'bg-blue-100 text-blue-700',
        $count <= 20 => 'bg-blue-300 text-blue-900',
        $count <= 50 => 'bg-emerald-400 text-white',
        $count <= 100 => 'bg-emerald-600 text-white',
        default => 'bg-emerald-800 text-white font-bold',
    };
}
```

---

## 7. Next Steps

### Phase 1 (Essential)
- [ ] Create `ScoreDistributionService` with base query
- [ ] Build Filament widget or page
- [ ] Add to dashboard or admin navigation

### Phase 2 (Enhancements)
- [ ] Add district/facility filters
- [ ] Date range filter
- [ ] CSV export
- [ ] Interactive tooltips

### Phase 3 (Future)
- [ ] Item-level drill-down view
- [ ] Score trajectory chart
- [ ] Integration with competency reports

---

## 8. Risks & Considerations

1. **Data volume**: With 11 tools × 45 items × 1000s of sessions, queries may be slow
   - **Mitigation**: Add database indexes on `tool_id`, `eval_date`, `sync_status`

2. **Null handling**: PPlusV2 uses `null` for "N/A" — different from legacy
   - **Mitigation**: Display null column explicitly or document filtering

3. **Real-time updates**: Filament caches widget data
   - **Mitigation**: Use polling or refresh button if needed

4. **Mobile responsiveness**: Wide table with 8 columns
   - **Mitigation**: Horizontal scroll or collapsible columns on mobile

---

## Summary

**The legacy `aggregateToolScoreCounts` component is:**
- A useful heat-mapped score distribution table
- Perfect for monitoring score patterns by disease tool
- Currently missing from PPlusV2 reporting dashboard

**Recommended path forward:**
- ✅ Create a new Filament widget or page in reporting/
- ✅ Query MySQL `session_item_scores` directly (not legacy CouchDB model)
- ✅ Add filters for district, date range
- ✅ Enhance with export & interactivity
- ✅ Integrate into admin dashboard navigation

**Fit in PPlusV2:**
- Primary: Dedicated "Score Analysis" page accessible from admin nav
- Secondary: Drill-down from EvaluationSessionResource
- Future: Component in competency report exports
