<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    binLabels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const totalCompetent = computed(() => props.summary.reduce((sum, r) => sum + r.total, 0));

const chartOptions = computed(() => ({
    xaxis: { categories: props.binLabels, title: { text: 'Days to Basic Competence' } },
    yaxis: { title: { text: 'Journeys' }, min: 0 },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    chart: { stacked: false },
}));

const fmt = (n) => (n == null ? '—' : Number(n).toFixed(1));
</script>

<template>
    <Head title="Time to Competence" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Time to Competence</h1>
            <p class="text-sm text-muted-foreground">
                Distribution of days from first session to basic competence across
                <span class="font-medium">{{ totalCompetent }}</span> completed journeys.
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
            ]"
        />

        <Card class="p-4">
            <h2 class="mb-3 text-base font-semibold">Competence Journey Length Distribution</h2>
            <div v-if="series.length > 0">
                <ApexChart type="bar" :series="series" :options="chartOptions" :height="320" />
            </div>
            <div v-else class="py-10 text-center text-sm text-muted-foreground">
                No completed journeys match the current filters.
            </div>
        </Card>

        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Summary by Tool</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium text-right">Completed</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Days</th>
                            <th class="px-4 py-3 font-medium text-right">Min Days</th>
                            <th class="px-4 py-3 font-medium text-right">Max Days</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="summary.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                No completed journeys match the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="row in summary"
                            :key="row.tool"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ row.tool }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.total }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ fmt(row.avgDays) }}d</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ row.minDays }}d</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.maxDays }}d</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ fmt(row.avgSessions) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
