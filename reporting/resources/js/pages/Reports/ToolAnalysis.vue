<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import MetricCard from '../../components/MetricCard.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, BarChart2, HelpCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selectedTool: { type: Object, default: null },
    summary: { type: Object, default: null },
    items: { type: Array, default: () => [] },
});

// Basic/Advanced/All — a filter over the already-loaded item list, not a
// server round-trip. Initialised from ?level= so a link (e.g. from Score
// Distribution's "Advanced: X%" figure) can land here pre-filtered; kept in
// sync with the URL via replaceState so the filtered view stays shareable
// without triggering a full Inertia navigation on every click.
const initialLevel = new URLSearchParams(window.location.search).get('level');
const itemLevel = ref(initialLevel === 'advanced' || initialLevel === 'basic' ? initialLevel : 'all');

function setItemLevel(level) {
    itemLevel.value = level;
    const url = new URL(window.location.href);
    if (level === 'all') url.searchParams.delete('level');
    else url.searchParams.set('level', level);
    window.history.replaceState({}, '', url);
}

const filteredItems = computed(() => {
    if (itemLevel.value === 'basic') return props.items.filter((i) => !i.isAdvanced);
    if (itemLevel.value === 'advanced') return props.items.filter((i) => i.isAdvanced);
    return props.items;
});

const advancedItemCount = computed(() => props.items.filter((i) => i.isAdvanced).length);

// ── insights ─────────────────────────────────────────────────────────────────
// Computed from every item regardless of the Basic/Advanced/All table filter
// above — insights describe the whole tool, not whatever slice is currently
// shown in the table.
const round2 = (n) => Math.round(n * 100) / 100;

// Category-level averages, so an M&E officer can see where to focus
// mentorship without scanning the full item table.
const categoryStats = computed(() => {
    const map = new Map();
    for (const item of props.items) {
        if (item.avgScore === null) continue;
        if (!map.has(item.category)) map.set(item.category, []);
        map.get(item.category).push(item.avgScore);
    }
    return [...map.entries()]
        .map(([category, scores]) => ({
            category,
            avg: round2(scores.reduce((a, b) => a + b, 0) / scores.length),
        }))
        .sort((a, b) => a.avg - b.avg);
});
const weakestCategory = computed(() => categoryStats.value[0] ?? null);
const strongestCategory = computed(() => categoryStats.value[categoryStats.value.length - 1] ?? null);

// Mirrors the "Items below threshold" KPI card (avg < 3.0) but names which
// items, so the number is something you can actually act on.
const needsAttention = computed(() =>
    props.items
        .filter((i) => i.avgScore !== null && i.avgScore < 3)
        .sort((a, b) => a.avgScore - b.avgScore)
        .slice(0, 5),
);

const SCORE_BUCKET_CLASS = { 1: 'bg-red-500', 2: 'bg-orange-400', 3: 'bg-amber-400', 4: 'bg-emerald-400', 5: 'bg-emerald-600' };

// The average alone can't distinguish "everyone scores a 2" from "half score
// 1, half score 5" — this is what actually produced that number.
function scoreBreakdown(item) {
    if (!item.timesScored) return [];
    return [1, 2, 3, 4, 5]
        .map((score) => ({ score, count: item.scoreCounts[score] ?? 0 }))
        .filter((s) => s.count > 0)
        .map((s) => ({ ...s, pct: round2((s.count / item.timesScored) * 100) }));
}

function scoreBreakdownText(item) {
    // Count alongside percentage — on a small n (common once filtered by
    // district/facility) a percentage alone can make one outlier session
    // read like a widespread pattern.
    return scoreBreakdown(item)
        .map((s) => `${s.pct}% (${s.count}) scored ${s.score}`)
        .join(' · ');
}

// Items whose N/A rate is at least double the tool's own average — a
// possible sign of low real-world applicability or evaluators skipping the
// item, worth checking rather than reading as a competency gap. Requires a
// handful of attempts so a single N/A on a rarely-scored item doesn't count.
const coverageOutliers = computed(() => {
    const withAttempts = props.items
        .map((i) => ({ ...i, totalAttempts: i.timesScored + i.countNa }))
        .filter((i) => i.totalAttempts >= 5);

    const totalNa = withAttempts.reduce((s, i) => s + i.countNa, 0);
    const totalAttempts = withAttempts.reduce((s, i) => s + i.totalAttempts, 0);
    const overallNaRate = totalAttempts > 0 ? totalNa / totalAttempts : 0;
    if (overallNaRate === 0) return [];

    return withAttempts
        .map((i) => ({ ...i, naRate: i.countNa / i.totalAttempts }))
        .filter((i) => i.naRate >= 0.2 && i.naRate >= overallNaRate * 2)
        .sort((a, b) => b.naRate - a.naRate)
        .slice(0, 5);
});

// Group items by category
const groupedItems = computed(() => {
    const map = new Map();
    for (const item of filteredItems.value) {
        if (!map.has(item.category)) {
            map.set(item.category, []);
        }
        map.get(item.category).push(item);
    }
    return [...map.entries()].map(([category, items]) => {
        const scored = items.filter((i) => i.avgScore !== null);
        const catAvg = scored.length > 0 ? scored.reduce((s, i) => s + i.avgScore, 0) / scored.length : null;
        return { category, items, catAvg };
    });
});

// Chart: horizontal bar, one bar per item
const chartHeight = computed(() => Math.max(320, filteredItems.value.length * 22));

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: filteredItems.value.map((i) => (i.avgScore !== null ? Number(i.avgScore.toFixed(2)) : 0)),
    },
]);

const chartOptions = computed(() => ({
    chart: { type: 'bar' },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '70%',
            colors: {
                ranges: [
                    { from: 0, to: 2.99, color: '#ef4444' },
                    { from: 3, to: 3.99, color: '#f59e0b' },
                    { from: 4, to: 5, color: '#10b981' },
                ],
            },
        },
    },
    xaxis: {
        categories: filteredItems.value.map((i) => i.number),
        min: 0,
        max: 5,
        title: { text: 'Average Score' },
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    dataLabels: {
        enabled: true,
        formatter: (val) => (val > 0 ? val.toFixed(2) : '—'),
        style: { fontSize: '10px' },
    },
    tooltip: {
        y: { formatter: (val) => (val > 0 ? val.toFixed(2) : 'Not scored') },
    },
    annotations: {
        xaxis: [
            {
                x: 4,
                borderColor: '#10b981',
                strokeDashArray: 4,
                label: {
                    text: 'Competency',
                    style: { color: '#fff', background: '#10b981', fontSize: '10px' },
                },
            },
        ],
    },
    legend: { show: false },
}));

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600 font-semibold';
};

const metric = (value, suffix = '') =>
    value === null || value === undefined ? '—' : `${value}${suffix}`;
</script>

<template>
    <Head title="Tool Analysis" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Tool Analysis</h1>
            <p class="text-sm text-muted-foreground">Item-level scoring breakdown for a selected evaluation tool.</p>
        </div>

        <FilterBar
            :filters="filters"
            :selects="[
                {
                    key: 'tool_id',
                    label: 'Tool',
                    placeholder: 'Select a tool',
                    options: tools.map((t) => ({ value: String(t.id), label: t.label })),
                },
                {
                    key: 'facility_id',
                    label: 'Facility',
                    placeholder: 'All facilities',
                    options: facilities.map((f) => ({ value: String(f.id), label: f.name })),
                },
                {
                    key: 'district_id',
                    label: 'District',
                    placeholder: 'All districts',
                    options: districts.map((d) => ({ value: String(d.id), label: d.name })),
                },
            ]"
        />

        <!-- Empty state -->
        <div
            v-if="!selectedTool"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center"
        >
            <BarChart2 class="mb-3 size-10 text-muted-foreground/40" />
            <p class="text-sm font-medium text-muted-foreground">Select a tool to view item-level analysis.</p>
        </div>

        <template v-else>
            <!-- KPI cards -->
            <section class="grid gap-4 sm:grid-cols-3">
                <MetricCard
                    label="Overall avg score"
                    :value="summary?.avgScore != null ? summary.avgScore.toFixed(2) : '—'"
                    helper="Across all items and sessions"
                />
                <MetricCard
                    label="Basic items at competency"
                    :value="summary?.pctAtCompetency != null ? `${summary.pctAtCompetency}%` : '—'"
                    :helper="
                        summary?.advancedScoredCount
                            ? `${summary?.scoredItems ?? 0} of ${summary?.totalItems ?? 0} items scored · Advanced: ${summary.pctAdvancedAtCompetency}%`
                            : `${summary?.scoredItems ?? 0} of ${summary?.totalItems ?? 0} items scored`
                    "
                />
                <MetricCard
                    label="Items below threshold"
                    :value="metric(summary?.itemsBelowThreshold)"
                    helper="Avg score < 3.0 — needs focus"
                />
            </section>

            <!-- Insights -->
            <Card class="space-y-3 p-4">
                <h2 class="text-sm font-semibold">Insights</h2>

                <p v-if="strongestCategory && weakestCategory && strongestCategory.category !== weakestCategory.category" class="text-xs text-muted-foreground">
                    Strongest category: <span class="font-medium text-emerald-600">{{ strongestCategory.category }} (avg {{ strongestCategory.avg.toFixed(2) }})</span>
                    · Weakest: <span class="font-medium text-red-600">{{ weakestCategory.category }} (avg {{ weakestCategory.avg.toFixed(2) }})</span>
                </p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border p-3">
                        <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-red-700">
                            <AlertTriangle class="size-3.5" />
                            Needs the most attention
                        </p>
                        <ul v-if="needsAttention.length" class="space-y-2.5 text-xs">
                            <li v-for="i in needsAttention" :key="i.id">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate">
                                        <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                        {{ i.title }}
                                    </span>
                                    <span class="shrink-0 font-semibold text-red-600">avg {{ i.avgScore.toFixed(2) }}</span>
                                </div>
                                <!-- Score breakdown — what actually produced that average -->
                                <div class="mt-1 flex h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        v-for="s in scoreBreakdown(i)"
                                        :key="s.score"
                                        :class="SCORE_BUCKET_CLASS[s.score]"
                                        :style="{ width: `${s.pct}%` }"
                                        :title="`${s.pct}% scored ${s.score} (${s.count} of ${i.timesScored})`"
                                    />
                                </div>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">{{ scoreBreakdownText(i) }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">No items averaging below 3.0.</p>
                    </div>

                    <div class="rounded-lg border p-3">
                        <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-amber-700">
                            <HelpCircle class="size-3.5" />
                            Coverage outliers
                        </p>
                        <ul v-if="coverageOutliers.length" class="space-y-1 text-xs">
                            <li v-for="i in coverageOutliers" :key="i.id" class="flex items-center justify-between gap-2">
                                <span class="truncate">
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                    {{ i.title }}
                                </span>
                                <span class="shrink-0 font-semibold text-amber-600" :title="`${i.countNa} of ${i.totalAttempts} attempts marked N/A`">
                                    {{ (i.naRate * 100).toFixed(0) }}% N/A
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">No items with an unusually high N/A rate.</p>
                    </div>
                </div>
            </Card>

            <!-- Horizontal bar chart -->
            <Card class="p-4">
                <h2 class="mb-3 text-base font-semibold">Score by Item</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Red &lt; 3.0 · Amber 3.0–3.99 · Green ≥ 4.0 · Dashed line = competency threshold
                </p>
                <ApexChart
                    type="bar"
                    :series="chartSeries"
                    :options="chartOptions"
                    :height="chartHeight"
                />
            </Card>

            <!-- Table grouped by category -->
            <Card>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <h2 class="text-base font-semibold">Item Detail — {{ selectedTool.label }}</h2>
                        <p class="text-xs text-muted-foreground">Click any item to view full analysis and journey breakdown.</p>
                    </div>
                    <div v-if="advancedItemCount > 0" class="flex items-center gap-1 rounded-md border p-0.5 text-xs">
                        <button
                            type="button"
                            class="rounded px-2 py-1 font-medium"
                            :class="itemLevel === 'all' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="setItemLevel('all')"
                        >
                            All ({{ items.length }})
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 font-medium"
                            :class="itemLevel === 'basic' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="setItemLevel('basic')"
                        >
                            Basic ({{ items.length - advancedItemCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 font-medium"
                            :class="itemLevel === 'advanced' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="setItemLevel('advanced')"
                            title="Advanced (grey) competencies — not required for Basic Competent status, only for Fully Competent"
                        >
                            Advanced ({{ advancedItemCount }})
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                                <th class="px-4 py-3 font-medium text-right">% ≥ 4</th>
                                <th class="px-4 py-3 font-medium text-right">Scored</th>
                                <th class="px-4 py-3 font-medium text-right">N/A</th>
                                <th class="px-4 py-3 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="group in groupedItems" :key="group.category">
                                <!-- Category header row -->
                                <tr class="bg-muted/40">
                                    <td colspan="7" class="px-4 py-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                {{ group.category }}
                                            </span>
                                            <span
                                                v-if="group.catAvg !== null"
                                                class="text-xs font-semibold tabular-nums"
                                                :class="scoreColor(group.catAvg)"
                                            >
                                                Avg {{ group.catAvg.toFixed(2) }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Item rows -->
                                <tr
                                    v-for="item in group.items"
                                    :key="item.id"
                                    class="border-t hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-mono text-xs font-medium text-muted-foreground">
                                        {{ item.number }}
                                    </td>
                                    <td class="max-w-sm px-4 py-3">
                                        <div class="flex flex-wrap items-start gap-2">
                                            <span class="line-clamp-2 text-sm">{{ item.title }}</span>
                                            <Badge v-if="item.isAdvanced" variant="outline" class="shrink-0 text-[10px]">
                                                Advanced
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span :class="scoreColor(item.avgScore)">
                                            {{ item.avgScore != null ? item.avgScore.toFixed(2) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ item.pctCompetent != null ? `${item.pctCompetent}%` : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ item.timesScored }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ item.countNa > 0 ? item.countNa : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <TableLink
                                            :href="`/tool-analysis/items/${item.id}`"
                                            tooltip="View item analysis and journey breakdown"
                                        >
                                            Analyse →
                                        </TableLink>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </main>
</template>
