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
    availableMonths: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const top15 = computed(() => props.rows.slice(0, 15));

const chartSeries = computed(() => [
    {
        name: 'Sessions',
        data: top15.value.map((r) => r.sessions),
    },
]);

const chartOptions = computed(() => ({
    chart: { type: 'bar' },
    plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
    xaxis: { title: { text: 'Sessions' } },
    yaxis: { categories: top15.value.map((r) => r.name) },
    dataLabels: { enabled: true, formatter: (val) => val },
    tooltip: { y: { formatter: (val) => `${val} sessions` } },
}));

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};

const monthLabel = (m) => {
    if (!m) return m;
    const [year, month] = m.split('-');
    return new Date(parseInt(year), parseInt(month) - 1).toLocaleString('default', { month: 'long', year: 'numeric' });
};

const monthOptions = computed(() =>
    props.availableMonths.map((m) => ({ value: m, label: monthLabel(m) })),
);
</script>

<template>
    <Head title="Evaluator Activity" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Evaluator Activity</h1>
            <p class="text-sm text-muted-foreground">Session counts and average scores per evaluator.</p>
        </div>

        <FilterBar
            :filters="filters"
            :selects="[
                {
                    key: 'month',
                    label: 'Month',
                    placeholder: 'All time',
                    options: monthOptions,
                },
                {
                    key: 'district_id',
                    label: 'District',
                    placeholder: 'All districts',
                    options: districts.map((d) => ({ value: String(d.id), label: d.name })),
                },
            ]"
        />

        <Card v-if="top15.length > 0" class="p-4">
            <h2 class="mb-3 text-base font-semibold">Sessions by Evaluator (top 15)</h2>
            <ApexChart type="bar" :series="chartSeries" :options="chartOptions" :height="Math.max(240, top15.length * 36)" />
        </Card>

        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">All Evaluators</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Evaluator</th>
                            <th class="px-4 py-3 font-medium text-right">Sessions</th>
                            <th class="px-4 py-3 font-medium text-right">Mentees</th>
                            <th class="px-4 py-3 font-medium text-right">Tools</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                No evaluator activity matches the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="(row, index) in rows"
                            :key="row.evaluatorId"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ row.sessions }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.mentees }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.tools }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="scoreColor(row.avgScore)">
                                    {{ row.avgScore != null ? row.avgScore.toFixed(2) : '—' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
