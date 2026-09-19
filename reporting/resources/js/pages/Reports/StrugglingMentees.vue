<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Card from '../../components/ui/Card.vue';
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import Pagination from '../../components/ui/Pagination.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mentees: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    summary: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Worst 30 mentees on this page for the chart — matches Hot Spots' "worst N"
// treatment, one row per mentee rather than per item.
const chartItems = computed(() => props.mentees.slice(0, 30));

const chartHeight = computed(() => Math.max(280, chartItems.value.length * 22));

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: chartItems.value.map((m) => (m.avgScore !== null ? Number(m.avgScore.toFixed(2)) : 0)),
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
        categories: chartItems.value.map((m) => m.mentee),
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
</script>

<template>
    <Head title="Struggling Mentees" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Struggling Mentees</h1>
            <p class="text-sm text-muted-foreground">
                Program-wide mentee rankings — each mentee's average across their most recent score on every tool
                they've been evaluated on, worst first. The mentee-side counterpart to Hot Spots.
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
            v-if="mentees.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center"
        >
            <Users class="mb-3 size-10 text-muted-foreground/40" />
            <p class="text-sm font-medium text-muted-foreground">No scored journeys found for the selected filters.</p>
        </div>

        <template v-else>
            <!-- KPI summary -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Mentees ranked"
                    :value="String(summary.totalMentees)"
                    helper="With at least one scored journey"
                />
                <MetricCard
                    label="Overall avg score"
                    :value="summary.avgScore != null ? summary.avgScore.toFixed(2) : '—'"
                    helper="Across all mentees' latest scores"
                />
                <MetricCard
                    label="Mentees below 3.0"
                    :value="String(summary.below3)"
                    helper="Significant gap — needs focused attention"
                />
                <MetricCard
                    label="Below competency (< 4.0)"
                    :value="String(summary.below4)"
                    helper="Not yet at competency threshold, overall"
                />
            </section>

            <!-- Worst performers chart -->
            <Card class="p-4">
                <h2 class="mb-1 text-base font-semibold">
                    Worst {{ Math.min(30, mentees.length) }} Mentees on This Page
                </h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Red &lt; 3.0 · Amber 3.0–3.99 · Green ≥ 4.0 · Dashed line = competency threshold · Chart reflects
                    only the current page — use filters to narrow further
                </p>
                <ApexChart
                    type="bar"
                    :series="chartSeries"
                    :options="chartOptions"
                    :height="chartHeight"
                />
            </Card>

            <!-- Full mentee table -->
            <Card>
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">All Mentees — Lowest to Highest</h2>
                    <p class="text-xs text-muted-foreground">
                        Average of each mentee's latest score across every tool they've been evaluated on.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Mentee</th>
                                <th class="px-4 py-3 font-medium">District / Facility</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                                <th class="px-4 py-3 font-medium text-right">Journeys</th>
                                <th class="px-4 py-3 font-medium text-right">Below Competency</th>
                                <th class="px-4 py-3 font-medium text-right">Open Gaps</th>
                                <th class="px-4 py-3 font-medium">Weakest Tool</th>
                                <th class="px-4 py-3 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in mentees"
                                :key="m.menteeId"
                                class="border-t hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">{{ m.mentee }}</td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ m.facility ?? '—' }}
                                    <span v-if="m.district" class="text-muted-foreground/60"> · {{ m.district }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span
                                        class="inline-block min-w-10 rounded-full px-2 py-0.5 text-center text-xs font-semibold"
                                        :class="scoreBg(m.avgScore)"
                                    >
                                        {{ m.avgScore.toFixed(2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                    {{ m.totalJourneys }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="m.journeysBelowCompetency > 0 ? 'font-semibold text-amber-600' : 'text-muted-foreground'">
                                        {{ m.journeysBelowCompetency }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="m.openGaps > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">
                                        {{ m.openGaps }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="text-foreground">{{ m.weakestTool }}</span>
                                    <span :class="['ml-1 tabular-nums text-xs', scoreColor(m.weakestToolScore)]">
                                        ({{ m.weakestToolScore.toFixed(2) }})
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <TableLink
                                        :href="`/journey-heatmap?group_id=${m.weakestGroupId}`"
                                        tooltip="View heatmap for this mentee's weakest tool"
                                    >
                                        View weakest →
                                    </TableLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t px-4 py-3">
                    <Pagination :meta="meta" />
                </div>
            </Card>
        </template>
    </main>
</template>
