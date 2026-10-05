<script setup>
import InsightsPanel from '../../components/InsightsPanel.vue';
import ApexChart from '../../components/ui/ApexChart.vue';
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import Pagination from '../../components/ui/Pagination.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Printer } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    items: { type: Array, default: () => [] },
    itemsMeta: { type: Object, default: null },
    binLabels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    menteeOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    printMode: { type: Boolean, default: false },
});

const totalStale = computed(() => props.itemsMeta?.total ?? 0);

const staleColor = (days) => {
    if (days >= 181) return 'font-semibold text-red-600';
    if (days >= 91) return 'font-medium text-orange-600';
    return 'font-medium text-amber-600';
};

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};

const printUrl = computed(() => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(props.filters)) {
        if (value !== '' && value != null) params.set(key, String(value));
    }
    params.set('print', '1');
    return `/needs-attention?${params.toString()}`;
});

onMounted(() => {
    if (props.printMode) {
        window.setTimeout(() => window.print(), 300);
    }
});

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

    <main class="needs-attention-report mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:py-0">
        <InsightsPanel
            class="print:hidden"
            summary="Finds journeys still in progress with no recorded session in the last 30 days."
            :points="[
                'This list is a follow-up queue based on journey status and recency; filter by mentee to focus on one person. It does not infer why mentorship paused.',
                'The 30-day interval is measured from the latest session date. Check the journey history before contacting a mentee.',
                'A journey may remain active while some items are already strong; use item-level results to focus the next visit.',
                'Basic competent journeys are not included because the required non-advanced items have reached 4 or 5.',
            ]"
        />
        <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-normal">Needs Attention</h1>
                <p class="text-sm text-muted-foreground">
                    In-progress journeys with no session in the last 30 days.
                    <span v-if="totalStale > 0" class="font-medium text-amber-600">{{ totalStale }} {{ totalStale === 1 ? 'journey' : 'journeys' }} overdue.</span>
                </p>
            </div>
            <a
                :href="printUrl"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-sm font-medium text-foreground shadow-sm hover:bg-muted/60"
            >
                <Printer class="size-4" />
                Print / Save PDF
            </a>
        </div>

        <FilterBar
            class="print:hidden"
            :filters="filters"
            :selects="[
                {
                    key: 'tool_id',
                    label: 'Tool',
                    placeholder: 'All tools',
                    options: tools.map((t) => ({ value: String(t.id), label: t.label })),
                },
                {
                    key: 'mentee_id',
                    label: 'Mentee',
                    placeholder: 'All mentees',
                    options: menteeOptions.map((m) => ({ value: String(m.id), label: m.name })),
                },
                {
                    key: 'district_id',
                    label: 'District',
                    placeholder: 'All districts',
                    options: districts.map((d) => ({ value: String(d.id), label: d.name })),
                },
            ]"
        />

        <div class="hidden print:block">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">PEN-Plus · Follow-up Report</p>
            <h1 class="mt-1 text-2xl font-bold">Needs Attention</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                In-progress journeys with no session in the last 30 days · {{ items.length }} journeys
            </p>
        </div>

        <Card>
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h2 class="text-base font-semibold">Overdue Journeys</h2>
                <AlertTriangle class="size-5 text-amber-500 print:hidden" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium">Facility / District</th>
                            <th class="px-4 py-3 font-medium text-right">Days Stale</th>
                            <th class="px-4 py-3 font-medium text-right">Sessions</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                            <th class="px-4 py-3 font-medium text-right">Open Gaps</th>
                            <th class="px-4 py-3 font-medium text-right print:hidden"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
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
                            <td class="px-4 py-3 text-muted-foreground">
                                <span>{{ item.facility ?? '—' }}</span>
                                <span v-if="item.district" class="block text-xs">{{ item.district }}</span>
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
                            <td class="px-4 py-3 text-right print:hidden">
                                <a
                                    :href="`/journey-sessions?group_id=${encodeURIComponent(item.groupId)}`"
                                    class="text-xs font-medium text-primary hover:underline"
                                >
                                    View journey
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!printMode" class="border-t px-4 py-3 print:hidden">
                <Pagination v-if="itemsMeta" :meta="itemsMeta" />
            </div>
        </Card>

        <Card v-if="series.length > 0" class="p-4 print:hidden">
            <h2 class="mb-3 text-base font-semibold">Staleness Distribution</h2>
            <ApexChart type="bar" :series="series" :options="chartOptions" :height="240" />
        </Card>
    </main>
</template>

<style>
@media print {
    @page {
        size: landscape;
        margin: 12mm;
    }

    .needs-attention-report {
        max-width: none !important;
        color: #111827;
    }

    .needs-attention-report tr {
        break-inside: avoid;
    }

    .needs-attention-report section {
        box-shadow: none !important;
    }

    .needs-attention-report .overflow-x-auto {
        overflow: visible !important;
    }

    .needs-attention-report table {
        width: 100%;
        font-size: 9pt;
    }

    .needs-attention-report thead {
        display: table-header-group;
    }
}
</style>
