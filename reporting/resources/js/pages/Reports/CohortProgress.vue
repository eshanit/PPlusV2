<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Card from '../../components/ui/Card.vue';
import InsightsPanel from '../../components/InsightsPanel.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Standing after the Nth session of each journey (latest score per item), not
// session averages, which cover a different set of items every session.
const chartSeries = computed(() => [
    {
        name: 'Required items achieved (%)',
        type: 'column',
        data: props.rows.map((r) => r.achievedPct ?? 0),
    },
    {
        name: 'Current average',
        type: 'line',
        data: props.rows.map((r) => r.avgScore),
    },
]);

const chartOptions = computed(() => ({
    xaxis: {
        categories: props.rows.map((r) => `Session ${r.session}`),
        title: { text: 'Session Number' },
    },
    yaxis: [
        { min: 0, max: 100, tickAmount: 5, title: { text: 'Required items achieved (%)' }, labels: { formatter: (val) => `${Math.round(val)}%` } },
        { opposite: true, min: 1, max: 5, tickAmount: 4, title: { text: 'Current average (all mentees)' } },
    ],
    colors: ['#16a34a', '#2563eb'],
    markers: { size: [0, 5] },
    stroke: { curve: 'smooth', width: [0, 3] },
    dataLabels: { enabled: false },
    tooltip: {
        custom: ({ series, seriesIndex, dataPointIndex }) => {
            const row = props.rows[dataPointIndex];
            return `<div class="px-3 py-2 text-sm">
                <strong>Session ${row.session}</strong><br/>
                Required items achieved: ${row.achievedPct ?? 0}%<br/>
                Current average: ${row.avgScore.toFixed(2)}<br/>
                Journeys: ${row.journeyCount}
            </div>`;
        },
    },
}));

const scoreColor = (score) => {
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};
</script>

<template>
    <Head title="Cohort Progress" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Cohort Progress</h1>
            <p class="text-sm text-muted-foreground">
                Where journeys stand after each session number: the average share of required items achieved, and the average of every item’s latest score. Comparable across sessions even though each session evaluates different items.
            </p>
        </div>
        <InsightsPanel
            summary="The line shows the cohort's average score at each journey session number."
            :points="[
                'A rising line suggests scores tend to improve over successive mentorship visits; a flat or falling line can indicate a need to review support.',
                'Each point averages journeys that have reached that session number, so later points usually include fewer journeys.',
                'Use the journey count beside each point to judge how much data supports it; compare like-for-like tool and district filters.',
                'The 4.0 line is a score reference, not proof that every item or every mentee is competent.',
            ]"
        />

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
                    key: 'district_id',
                    label: 'District',
                    placeholder: 'All districts',
                    options: districts.map((d) => ({ value: String(d.id), label: d.name })),
                },
            ]"
        />

        <Card class="p-4">
            <h2 class="mb-3 text-base font-semibold">Score Progression by Session</h2>
            <div v-if="rows.length > 0">
                <ApexChart type="line" :series="chartSeries" :options="chartOptions" :height="340" />
            </div>
            <div v-else class="py-10 text-center text-sm text-muted-foreground">
                No session data matches the current filters.
            </div>
        </Card>

        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Session Breakdown</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Session #</th>
                            <th class="px-4 py-3 font-medium text-right">Current avg</th>
                            <th class="px-4 py-3 font-medium text-right">Journeys</th>
                            <th class="px-4 py-3 font-medium">Required achieved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                No session data matches the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.session"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-medium text-muted-foreground">{{ row.session }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="scoreColor(row.avgScore)">{{ row.avgScore.toFixed(2) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.journeyCount }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-32 rounded-full bg-muted">
                                        <div
                                            class="h-1.5 rounded-full bg-emerald-600 transition-all"
                                            :style="{ width: `${row.achievedPct ?? 0}%` }"
                                        />
                                    </div>
                                    <span class="text-xs tabular-nums text-muted-foreground">{{ row.achievedPct ?? 0 }}%</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
