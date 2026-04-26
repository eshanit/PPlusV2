<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tools: { type: Array, default: () => [] },
    journeys: { type: Array, default: () => [] },
    trajectory: { type: Array, default: () => [] },
    selectedJourney: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

const toolId = ref(props.filters.tool_id ?? '');
const groupId = ref(props.filters.group_id ?? '');

watch(toolId, (newVal) => {
    groupId.value = '';
    router.get('/score-trajectory', newVal ? { tool_id: newVal } : {}, {
        preserveState: true,
        preserveScroll: false,
        replace: true,
    });
});

watch(groupId, (newVal) => {
    if (!newVal) return;
    router.get(
        '/score-trajectory',
        { tool_id: toolId.value, group_id: newVal },
        { preserveState: true, preserveScroll: false, replace: true },
    );
});

const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.trajectory.map((s) => s.avgScore ?? null),
    },
]);

const chartOptions = computed(() => ({
    xaxis: {
        categories: props.trajectory.map((s) => `Session ${s.session}`),
        title: { text: 'Session' },
    },
    yaxis: {
        min: 1,
        max: 5,
        title: { text: 'Average Score' },
        tickAmount: 4,
    },
    markers: { size: 5 },
    stroke: { curve: 'smooth', width: 2 },
    annotations: props.selectedJourney?.sessionsToBasic
        ? {
              xaxis: [
                  {
                      x: `Session ${props.selectedJourney.sessionsToBasic}`,
                      borderColor: '#22c55e',
                      label: {
                          text: 'Basic Competent',
                          style: { color: '#fff', background: '#22c55e' },
                      },
                  },
              ],
          }
        : {},
    dataLabels: { enabled: false },
    tooltip: {
        y: { formatter: (val) => (val != null ? val.toFixed(2) : '—') },
    },
}));

const statusVariant = (status) =>
    ({ fully_competent: 'success', basic_competent: 'secondary', in_progress: 'warning' }[status] ?? 'outline');

const statusLabel = (status) =>
    ({ fully_competent: 'Fully Competent', basic_competent: 'Basic Competent', in_progress: 'In Progress' }[status] ?? status);

const phaseLabel = (phase) =>
    ({
        initial_intensive: 'Initial Intensive',
        ongoing: 'Ongoing',
        supervision: 'Supervision',
    }[phase] ?? phase ?? '—');

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};
</script>

<template>
    <Head title="Score Trajectory" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Score Trajectory</h1>
            <p class="text-sm text-muted-foreground">Session-by-session average score for a single mentee journey.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-muted-foreground">Tool</label>
                <select
                    v-model="toolId"
                    class="h-8 rounded-md border border-border bg-card px-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                >
                    <option value="">All tools</option>
                    <option v-for="t in tools" :key="t.id" :value="String(t.id)">{{ t.label }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-muted-foreground">Journey</label>
                <select
                    v-model="groupId"
                    :disabled="!toolId || journeys.length === 0"
                    class="h-8 min-w-56 rounded-md border border-border bg-card px-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50"
                >
                    <option value="">{{ toolId ? 'Select a journey' : 'Select a tool first' }}</option>
                    <option v-for="j in journeys" :key="j.groupId" :value="j.groupId">{{ j.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="!selectedJourney" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
            <TrendingUp class="mb-3 size-10 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">Select a tool and journey to view the score trajectory.</p>
        </div>

        <template v-else>
            <Card class="p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-lg font-semibold">{{ selectedJourney.mentee }}</p>
                        <p class="text-sm text-muted-foreground">{{ selectedJourney.tool }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Badge :variant="statusVariant(selectedJourney.status)">{{ statusLabel(selectedJourney.status) }}</Badge>
                        <span class="text-sm text-muted-foreground">{{ selectedJourney.totalSessions }} sessions</span>
                        <span v-if="selectedJourney.basicCompetentAt" class="text-sm text-muted-foreground">
                            Competent: {{ selectedJourney.basicCompetentAt }}
                        </span>
                    </div>
                </div>

                <div v-if="trajectory.length > 0" class="mt-4">
                    <ApexChart type="line" :series="chartSeries" :options="chartOptions" :height="300" />
                </div>
                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                    No session score data available for this journey.
                </div>
            </Card>

            <Card v-if="trajectory.length > 0">
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Session Details</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Phase</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                                <th class="px-4 py-3 font-medium text-right">Items Scored</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in trajectory"
                                :key="s.session"
                                class="border-t hover:bg-muted/30"
                                :class="{ 'bg-emerald-50': selectedJourney.sessionsToBasic === s.session }"
                            >
                                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ s.session }}</td>
                                <td class="px-4 py-3">{{ s.date }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ phaseLabel(s.phase) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="scoreColor(s.avgScore)">{{ s.avgScore != null ? s.avgScore.toFixed(2) : '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ s.scoredItems }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </main>
</template>
