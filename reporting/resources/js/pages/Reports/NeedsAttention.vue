<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import Pagination from '../../components/ui/Pagination.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    items: { type: Array, default: () => [] },
    itemsMeta: { type: Object, default: null },
    binLabels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const totalStale = computed(() => props.itemsMeta?.total ?? 0);

const chartOptions = computed(() => ({
    xaxis: { categories: props.binLabels },
    yaxis: { title: { text: 'Journeys' }, min: 0 },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
}));
</script>

<template>
    <Head title="Needs Attention" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Needs Attention</h1>
            <p class="text-sm text-muted-foreground">
                In-progress journeys with no session in the last 30 days.
                <span v-if="totalStale > 0" class="font-medium text-amber-600">{{ totalStale }} {{ totalStale === 1 ? 'journey' : 'journeys' }} overdue.</span>
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

        <Card v-if="series.length > 0" class="p-4">
            <h2 class="mb-3 text-base font-semibold">Staleness Distribution</h2>
            <ApexChart type="bar" :series="series" :options="chartOptions" :height="240" />
        </Card>

        <Card>
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h2 class="text-base font-semibold">Overdue Journeys</h2>
                <AlertTriangle class="size-5 text-amber-500" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="hidden px-4 py-3 font-medium sm:table-cell">District</th>
                            <th class="px-4 py-3 font-medium text-right">Days Stale</th>
                            <th class="px-4 py-3 font-medium text-right">Sessions</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                            <th class="px-4 py-3 font-medium text-right">Open Gaps</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                No overdue journeys. All in-progress journeys have recent activity.
                            </td>
                        </tr>
                        <tr
                            v-for="item in items"
                            :key="item.groupId"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ item.mentee }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ item.tool }}</td>
                            <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                {{ item.district ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="staleColor(item.daysStale)">{{ item.daysStale }}d</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ item.totalSessions }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="scoreColor(item.latestAvgScore)">
                                    {{ item.latestAvgScore != null ? item.latestAvgScore.toFixed(1) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span v-if="item.openGaps > 0" class="font-semibold text-red-600">{{ item.openGaps }}</span>
                                <span v-else class="text-muted-foreground/50">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t px-4 py-3">
                <Pagination v-if="itemsMeta" :meta="itemsMeta" />
            </div>
        </Card>
    </main>
</template>
