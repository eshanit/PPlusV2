<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import MetricCard from '../../components/MetricCard.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BarChart2 } from 'lucide-vue-next';
import { computed } from 'vue';

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

// Group items by category
const groupedItems = computed(() => {
    const map = new Map();
    for (const item of props.items) {
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
const chartHeight = computed(() => Math.max(320, props.items.length * 22));

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.items.map((i) => (i.avgScore !== null ? Number(i.avgScore.toFixed(2)) : 0)),
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
        categories: props.items.map((i) => i.number),
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
                    label="Items at competency"
                    :value="summary?.pctAtCompetency != null ? `${summary.pctAtCompetency}%` : '—'"
                    :helper="`${summary?.scoredItems ?? 0} of ${summary?.totalItems ?? 0} items scored`"
                />
                <MetricCard
                    label="Items below threshold"
                    :value="metric(summary?.itemsBelowThreshold)"
                    helper="Avg score < 3.0 — needs focus"
                />
            </section>

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
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Item Detail — {{ selectedTool.label }}</h2>
                    <p class="text-xs text-muted-foreground">Click any item to view full analysis and journey breakdown.</p>
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
