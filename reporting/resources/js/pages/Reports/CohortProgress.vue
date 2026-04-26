<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Card from '../../components/ui/Card.vue';
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

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.rows.map((r) => r.avgScore),
    },
]);

const chartOptions = computed(() => ({
    xaxis: {
        categories: props.rows.map((r) => `Session ${r.session}`),
        title: { text: 'Session Number' },
    },
    yaxis: {
        min: 1,
        max: 5,
        tickAmount: 4,
        title: { text: 'Average Score (all mentees)' },
    },
    markers: { size: 5 },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    annotations: {
        yaxis: [
            {
                y: 4,
                borderColor: '#22c55e',
                label: {
                    text: 'Competency Threshold (4.0)',
                    style: { color: '#fff', background: '#22c55e' },
                },
            },
        ],
    },
    tooltip: {
        custom: ({ series, seriesIndex, dataPointIndex }) => {
            const row = props.rows[dataPointIndex];
            return `<div class="px-3 py-2 text-sm">
                <strong>Session ${row.session}</strong><br/>
                Avg score: ${row.avgScore.toFixed(2)}<br/>
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
                Average score across all mentees by session number — shows cohort-wide learning trajectory.
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
                            <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                            <th class="px-4 py-3 font-medium text-right">Journeys</th>
                            <th class="px-4 py-3 font-medium">Progress</th>
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
                                            class="h-1.5 rounded-full bg-primary transition-all"
                                            :style="{ width: `${((row.avgScore - 1) / 4) * 100}%` }"
                                        />
                                    </div>
                                    <span class="text-xs text-muted-foreground">/ 5</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
