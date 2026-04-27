<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    item: { type: Object, required: true },
    stats: { type: Object, required: true },
    distribution: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    journeys: { type: Array, default: () => [] },
});

// Score distribution bar chart
const distSeries = computed(() => [
    {
        name: 'Sessions',
        data: props.distribution.map((d) => d.count),
    },
]);

const distOptions = computed(() => ({
    chart: { type: 'bar' },
    plotOptions: { bar: { distributed: true, columnWidth: '50%' } },
    colors: ['#ef4444', '#f97316', '#f59e0b', '#10b981', '#059669'],
    xaxis: {
        categories: ['Score 1', 'Score 2', 'Score 3', 'Score 4', 'Score 5'],
        title: { text: 'Score Value' },
    },
    yaxis: { title: { text: 'Times Given' }, min: 0 },
    dataLabels: { enabled: true },
    legend: { show: false },
    tooltip: {
        y: { formatter: (val) => `${val} sessions` },
    },
}));

// Score trend line chart
const trendSeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.trend.map((t) => t.avgScore),
    },
]);

const trendOptions = computed(() => ({
    xaxis: {
        categories: props.trend.map((t) => `Session ${t.sessionNumber}`),
        title: { text: 'Session Number (across all journeys)' },
    },
    yaxis: {
        min: 1,
        max: 5,
        tickAmount: 4,
        title: { text: 'Avg Score' },
    },
    markers: { size: 5 },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    annotations: {
        yaxis: [
            {
                y: 4,
                borderColor: '#10b981',
                strokeDashArray: 4,
                label: {
                    text: 'Competency (4.0)',
                    position: 'left',
                    style: { color: '#fff', background: '#10b981', fontSize: '11px' },
                },
            },
        ],
    },
    tooltip: {
        y: { formatter: (val) => (val != null ? val.toFixed(2) : '—') },
    },
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
    <Head :title="`Item Analysis — ${item.number}`" />

    <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/tool-analysis" class="flex items-center gap-1 hover:text-foreground">
                <ArrowLeft class="size-4" />
                Tool Analysis
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <Link :href="`/tool-analysis?tool_id=${item.tool.id}`" class="hover:text-foreground">
                {{ item.tool.label }}
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <span class="text-foreground">{{ item.number }}</span>
        </div>

        <!-- Item header card -->
        <Card class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold text-muted-foreground">{{ item.number }}</span>
                        <Badge v-if="item.isAdvanced" variant="outline">Advanced</Badge>
                    </div>
                    <p class="text-lg font-semibold leading-snug">{{ item.title }}</p>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <span class="text-sm text-muted-foreground">{{ item.tool.label }}</span>
                        <span class="text-muted-foreground/40">·</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {{ item.category }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="stats.avgScore !== null"
                    class="rounded-xl px-5 py-3 text-center"
                    :class="scoreBg(stats.avgScore)"
                >
                    <p class="text-3xl font-bold tabular-nums">{{ stats.avgScore.toFixed(2) }}</p>
                    <p class="mt-0.5 text-xs font-medium">Avg score</p>
                </div>
            </div>
        </Card>

        <!-- 4 stat boxes -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="p-4">
                <p class="text-xs text-muted-foreground">Avg score</p>
                <p class="mt-1 text-2xl font-bold tabular-nums" :class="scoreColor(stats.avgScore)">
                    {{ stats.avgScore != null ? stats.avgScore.toFixed(2) : '—' }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">out of 5.0</p>
            </Card>
            <Card class="p-4">
                <p class="text-xs text-muted-foreground">% sessions ≥ 4</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">
                    {{ stats.pctCompetent != null ? `${stats.pctCompetent}%` : '—' }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">at competency level</p>
            </Card>
            <Card class="p-4">
                <p class="text-xs text-muted-foreground">Times scored</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ stats.timesScored }}</p>
                <p class="mt-1 text-xs text-muted-foreground">across all sessions</p>
            </Card>
            <Card class="p-4">
                <p class="text-xs text-muted-foreground">Journeys</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ stats.journeyCount }}</p>
                <p class="mt-1 text-xs text-muted-foreground">mentee journeys scored this item</p>
            </Card>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">

            <!-- Score distribution chart -->
            <Card class="p-4">
                <h2 class="mb-1 text-base font-semibold">Score Distribution</h2>
                <p class="mb-4 text-xs text-muted-foreground">How often each score value was given across all sessions.</p>
                <div v-if="distribution.some((d) => d.count > 0)">
                    <ApexChart type="bar" :series="distSeries" :options="distOptions" :height="260" />
                </div>
                <div v-else class="py-10 text-center text-sm text-muted-foreground">
                    No score data available.
                </div>
            </Card>

            <!-- Score trend chart -->
            <Card class="p-4">
                <h2 class="mb-1 text-base font-semibold">Score Trend by Session</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Avg score at each session number (sessions with ≥ 3 journeys shown).
                </p>
                <div v-if="trend.length > 0">
                    <ApexChart type="line" :series="trendSeries" :options="trendOptions" :height="260" />
                </div>
                <div v-else class="py-10 text-center text-sm text-muted-foreground">
                    Not enough data to show a trend (requires ≥ 3 journeys per session).
                </div>
            </Card>

        </div>

        <!-- Journey breakdown table -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Journey Breakdown</h2>
                <p class="text-xs text-muted-foreground">Latest score per mentee journey, worst first.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Facility</th>
                            <th class="px-4 py-3 font-medium text-right">Latest Score</th>
                            <th class="px-4 py-3 font-medium text-right">Times Scored</th>
                            <th class="px-4 py-3 font-medium text-right">Last Scored</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="journeys.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                No journey data available for this item.
                            </td>
                        </tr>
                        <tr
                            v-for="j in journeys"
                            :key="j.evaluationGroupId"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ j.mentee }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ j.facility ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <span
                                    class="inline-block min-w-8 rounded-full px-2 py-0.5 text-center text-xs font-semibold tabular-nums"
                                    :class="scoreBg(j.latestScore)"
                                >
                                    {{ j.latestScore }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ j.timesScored }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ j.scoreDate }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <TableLink
                                        :href="`/journey-heatmap?group_id=${encodeURIComponent(j.evaluationGroupId)}`"
                                        tooltip="View competency heatmap for this journey"
                                    >
                                        Heatmap →
                                    </TableLink>
                                    <TableLink
                                        :href="`/journey-sessions?group_id=${encodeURIComponent(j.evaluationGroupId)}`"
                                        tooltip="View all sessions for this journey"
                                    >
                                        Sessions →
                                    </TableLink>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

    </main>
</template>
