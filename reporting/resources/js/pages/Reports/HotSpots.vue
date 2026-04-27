<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Flame } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    items: { type: Array, default: () => [] },
    toolBreakdown: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Bottom 30 worst performers for the chart
const chartItems = computed(() => props.items.slice(0, 30));

const chartHeight = computed(() => Math.max(280, chartItems.value.length * 22));

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: chartItems.value.map((i) => (i.avgScore !== null ? Number(i.avgScore.toFixed(2)) : 0)),
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
        categories: chartItems.value.map((i) => `${i.number} (${i.tool.split(' ')[0]})`),
        min: 0,
        max: 5,
        title: { text: 'Average Score' },
    },
    yaxis: { labels: { style: { fontSize: '10px' } } },
    dataLabels: {
        enabled: true,
        formatter: (val) => (val > 0 ? val.toFixed(2) : '—'),
        style: { fontSize: '10px' },
    },
    tooltip: {
        y: { formatter: (val) => (val > 0 ? val.toFixed(2) : '—') },
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

const scoreBg = (score) => {
    if (score >= 4) return 'bg-emerald-100 text-emerald-700';
    if (score >= 3) return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-700';
};

const toolScoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600 font-semibold';
};
</script>

<template>
    <Head title="Hot Spots" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Competency Hot Spots</h1>
            <p class="text-sm text-muted-foreground">
                Program-wide item rankings — which competencies are consistently scoring lowest across all tools and mentees.
            </p>
        </div>

        <FilterBar
            :filters="filters"
            :selects="[
                {
                    key: 'tool_id',
                    label: 'Tool',
                    placeholder: 'All tools',
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
            v-if="items.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center"
        >
            <Flame class="mb-3 size-10 text-muted-foreground/40" />
            <p class="text-sm font-medium text-muted-foreground">No scored items found for the selected filters.</p>
        </div>

        <template v-else>
            <!-- KPI summary -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Items scored"
                    :value="String(summary.totalScored)"
                    helper="Distinct competency items with data"
                />
                <MetricCard
                    label="Overall avg score"
                    :value="summary.avgScore != null ? summary.avgScore.toFixed(2) : '—'"
                    helper="Across all scored items"
                />
                <MetricCard
                    label="Items below 3.0"
                    :value="String(summary.below3)"
                    helper="Significant gap — needs focused attention"
                />
                <MetricCard
                    label="Below competency (< 4.0)"
                    :value="String(summary.below4)"
                    helper="Items not yet at competency threshold"
                />
            </section>

            <!-- Worst performers chart -->
            <Card class="p-4">
                <h2 class="mb-1 text-base font-semibold">
                    Worst {{ Math.min(30, items.length) }} Items by Average Score
                </h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Red &lt; 3.0 · Amber 3.0–3.99 · Green ≥ 4.0 · Dashed line = competency threshold · Tool abbreviation in parentheses
                </p>
                <ApexChart
                    type="bar"
                    :series="chartSeries"
                    :options="chartOptions"
                    :height="chartHeight"
                />
            </Card>

            <!-- Tool breakdown -->
            <Card>
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Performance by Tool</h2>
                    <p class="text-xs text-muted-foreground">Average scores and item counts per evaluation tool.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Tool</th>
                                <th class="px-4 py-3 font-medium text-right">Items Scored</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                                <th class="px-4 py-3 font-medium text-right">Items &lt; 3.0</th>
                                <th class="px-4 py-3 font-medium text-right">% at Competency</th>
                                <th class="px-4 py-3 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in toolBreakdown"
                                :key="t.toolId"
                                class="border-t hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">{{ t.tool }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ t.totalItems }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="toolScoreColor(t.avgScore)">
                                        {{ t.avgScore != null ? t.avgScore.toFixed(2) : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="t.itemsBelow3 > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">
                                        {{ t.itemsBelow3 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                    {{ t.pctAtCompetency != null ? `${t.pctAtCompetency}%` : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <TableLink
                                        :href="`/tool-analysis?tool_id=${t.toolId}`"
                                        tooltip="View item-level analysis for this tool"
                                    >
                                        Analyse →
                                    </TableLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <!-- Full item table -->
            <Card>
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">All Items — Lowest to Highest</h2>
                    <p class="text-xs text-muted-foreground">
                        Every scored competency item ranked by average score.
                        <span class="ml-1 inline-flex items-center gap-1 text-orange-600">
                            <AlertTriangle class="size-3" /> = high-risk item
                        </span>
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Tool</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                                <th class="px-4 py-3 font-medium text-right">% ≥ 4</th>
                                <th class="px-4 py-3 font-medium text-right">Times Scored</th>
                                <th class="px-4 py-3 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="border-t hover:bg-muted/30"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-xs font-medium text-muted-foreground">{{ item.number }}</span>
                                        <AlertTriangle
                                            v-if="item.isCritical"
                                            class="size-3 text-orange-500"
                                            title="High-risk item"
                                        />
                                    </div>
                                </td>
                                <td class="max-w-sm px-4 py-3">
                                    <div class="flex flex-wrap items-start gap-1.5">
                                        <span class="line-clamp-2 text-sm">{{ item.title }}</span>
                                        <Badge v-if="item.isAdvanced" variant="outline" class="shrink-0 text-[10px]">
                                            Advanced
                                        </Badge>
                                    </div>
                                    <p class="mt-0.5 text-[10px] text-muted-foreground">{{ item.category }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">{{ item.tool }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span
                                        class="inline-block min-w-10 rounded-full px-2 py-0.5 text-center text-xs font-semibold"
                                        :class="scoreBg(item.avgScore)"
                                    >
                                        {{ item.avgScore != null ? item.avgScore.toFixed(2) : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                    {{ item.pctCompetent != null ? `${item.pctCompetent}%` : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                    {{ item.timesScored }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <TableLink
                                        :href="`/tool-analysis/items/${item.id}`"
                                        tooltip="View full item analysis"
                                    >
                                        Analyse →
                                    </TableLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </main>
</template>
