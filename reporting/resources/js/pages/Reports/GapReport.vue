<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    gap: { type: Object, required: true },
    journey: { type: Object, required: true },
    stats: { type: Object, required: true },
    trajectory: { type: Array, default: () => [] },
    domainLabels: { type: Object, default: () => ({}) },
    supervisionLabels: { type: Object, default: () => ({}) },
});

const domainConfig = {
    knowledge: 'bg-blue-100 text-blue-700',
    critical_reasoning: 'bg-purple-100 text-purple-700',
    clinical_skills: 'bg-emerald-100 text-emerald-700',
    communication: 'bg-amber-100 text-amber-700',
    attitude: 'bg-rose-100 text-rose-700',
};

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};

const scoreDelta = computed(() => {
    if (props.stats.avgScoreBefore == null || props.stats.avgScoreAfter == null) return null;
    return +(props.stats.avgScoreAfter - props.stats.avgScoreBefore).toFixed(2);
});

// Trajectory chart
const chartSeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.trajectory.map((s) => s.avgScore ?? null),
    },
]);

const chartOptions = computed(() => {
    const identifiedAtSession = props.stats.sessionsAtIdentification;

    const annotations =
        identifiedAtSession > 0
            ? {
                  xaxis: [
                      {
                          x: `Session ${identifiedAtSession}`,
                          borderColor: '#f97316',
                          strokeDashArray: 4,
                          label: {
                              text: 'Gap Identified',
                              style: { color: '#fff', background: '#f97316', fontSize: '11px' },
                              position: 'top',
                          },
                      },
                  ],
              }
            : {};

    return {
        annotations,
        xaxis: {
            categories: props.trajectory.map((s) => `Session ${s.sessionNumber}`),
            title: { text: 'Session' },
        },
        yaxis: {
            min: 1,
            max: 5,
            title: { text: 'Avg Score' },
            tickAmount: 4,
        },
        markers: { size: 5 },
        stroke: { curve: 'smooth', width: 2 },
        colors: ['#6366f1'],
        dataLabels: { enabled: false },
    };
});
</script>

<template>
    <Head :title="`Gap Report — ${journey.menteeName}`" />

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/journey-status" class="flex items-center gap-1 hover:text-foreground">
                <ArrowLeft class="size-4" />
                Journey Status
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <Link
                :href="`/journey-gaps?group_id=${encodeURIComponent(journey.groupId)}`"
                class="hover:text-foreground"
            >
                {{ journey.menteeName }} — Gaps
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <span class="text-foreground">Gap Analysis</span>
        </div>

        <!-- Status badge -->
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold">Gap Analysis</h1>
            <span
                v-if="gap.isResolved"
                class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700"
            >
                Resolved
            </span>
            <span
                v-else
                class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700"
            >
                Open
            </span>
        </div>

        <!-- Gap detail card -->
        <Card class="p-4 space-y-4">
            <div>
                <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Description</p>
                <p class="text-sm leading-relaxed">{{ gap.description }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Domains -->
                <div>
                    <p class="mb-1.5 text-xs font-medium uppercase text-muted-foreground">Domains</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="d in gap.domains"
                            :key="d"
                            :class="domainConfig[d] ?? 'bg-muted text-muted-foreground'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                            {{ domainLabels[d] ?? d }}
                        </span>
                    </div>
                </div>

                <!-- Identified at -->
                <div>
                    <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Identified</p>
                    <p class="text-sm tabular-nums">{{ gap.identifiedAt }}</p>
                </div>

                <!-- Supervision level -->
                <div v-if="gap.supervisionLevel">
                    <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Supervision Level</p>
                    <p class="text-sm">{{ supervisionLabels[gap.supervisionLevel] ?? gap.supervisionLevel }}</p>
                </div>

                <!-- Timeline -->
                <div v-if="gap.timeline">
                    <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Planned Timeline</p>
                    <p class="text-sm">{{ gap.timeline }}</p>
                </div>

                <!-- Coverage -->
                <div>
                    <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Covered in Mentorship</p>
                    <p class="text-sm">
                        <span v-if="gap.coveredInMentorship === true" class="text-emerald-600">Yes</span>
                        <span v-else-if="gap.coveredInMentorship === false" class="text-red-500">No</span>
                        <span v-else class="text-muted-foreground">Not specified</span>
                    </p>
                </div>

                <div v-if="gap.coveringLater">
                    <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Covering Later</p>
                    <p class="text-sm text-amber-600">Planned for a later session</p>
                </div>
            </div>
        </Card>

        <!-- Context stats row -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card class="p-4 text-center">
                <p class="text-2xl font-bold tabular-nums">{{ stats.sessionsAtIdentification }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Sessions when identified</p>
            </Card>
            <Card class="p-4 text-center">
                <p class="text-2xl font-bold tabular-nums">{{ stats.sessionsAfter }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Sessions since</p>
            </Card>
            <Card class="p-4 text-center">
                <p
                    class="text-2xl font-bold tabular-nums"
                    :class="gap.isResolved ? 'text-emerald-600' : 'text-orange-600'"
                >
                    {{ gap.daysOpen }}d
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ gap.isResolved ? 'Days to resolve' : 'Days open' }}
                </p>
            </Card>
            <Card class="p-4 text-center">
                <template v-if="scoreDelta !== null">
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="scoreDelta > 0 ? 'text-emerald-600' : scoreDelta < 0 ? 'text-red-600' : 'text-muted-foreground'"
                    >
                        {{ scoreDelta > 0 ? '+' : '' }}{{ scoreDelta }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Score change (before → after)</p>
                </template>
                <template v-else>
                    <p class="text-2xl font-bold tabular-nums text-muted-foreground">—</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Score change</p>
                </template>
            </Card>
        </div>

        <!-- Score comparison -->
        <div v-if="stats.avgScoreBefore != null || stats.avgScoreAfter != null" class="grid grid-cols-2 gap-3">
            <Card class="p-4 text-center">
                <p class="text-xs font-medium uppercase text-muted-foreground">Avg Score Before Gap</p>
                <p class="mt-1 text-3xl font-bold tabular-nums" :class="scoreColor(stats.avgScoreBefore)">
                    {{ stats.avgScoreBefore != null ? stats.avgScoreBefore.toFixed(2) : '—' }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    across {{ stats.sessionsAtIdentification }} session{{ stats.sessionsAtIdentification !== 1 ? 's' : '' }}
                </p>
            </Card>
            <Card class="p-4 text-center">
                <p class="text-xs font-medium uppercase text-muted-foreground">Avg Score After Gap</p>
                <p class="mt-1 text-3xl font-bold tabular-nums" :class="scoreColor(stats.avgScoreAfter)">
                    {{ stats.avgScoreAfter != null ? stats.avgScoreAfter.toFixed(2) : '—' }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    across {{ stats.sessionsAfter }} session{{ stats.sessionsAfter !== 1 ? 's' : '' }}
                </p>
            </Card>
        </div>

        <!-- Journey context -->
        <Card class="p-4">
            <p class="mb-3 text-xs font-medium uppercase text-muted-foreground">Journey Context</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div>
                    <span class="text-muted-foreground">Mentee: </span>
                    <span class="font-medium">{{ journey.menteeName }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Evaluator: </span>
                    <span>{{ journey.evaluatorName }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Tool: </span>
                    <span>{{ journey.toolLabel }}</span>
                </div>
                <div v-if="journey.facility" class="flex items-center gap-1">
                    <MapPin class="size-3 shrink-0 opacity-50" />
                    <span>{{ journey.facility }}</span>
                </div>
                <div v-if="journey.district">
                    <span class="text-muted-foreground">District: </span>
                    <span>{{ journey.district }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Total sessions: </span>
                    <Link
                        :href="`/journey-sessions?group_id=${encodeURIComponent(journey.groupId)}`"
                        class="font-semibold text-teal-600 hover:underline"
                    >
                        {{ journey.totalSessions }}
                    </Link>
                </div>
            </div>
        </Card>

        <!-- Score trajectory chart -->
        <Card v-if="trajectory.length > 0">
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Score Trajectory</h2>
                <p class="text-xs text-muted-foreground">
                    Average score per session. The orange line marks when this gap was identified.
                </p>
            </div>
            <div class="p-4">
                <ApexChart type="line" :series="chartSeries" :options="chartOptions" :height="260" />
            </div>
            <div class="border-t px-4 py-2">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                    <span v-for="s in trajectory" :key="s.sessionId">
                        <Link :href="`/sessions/${s.sessionId}`" class="hover:text-primary hover:underline">
                            Session {{ s.sessionNumber }}
                        </Link>
                        <span v-if="s.date"> — {{ s.date }}</span>
                    </span>
                </div>
            </div>
        </Card>

        <!-- Resolution card -->
        <Card v-if="gap.isResolved" class="border-emerald-200 p-4">
            <p class="mb-2 text-xs font-medium uppercase text-emerald-700">Resolution</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-muted-foreground">Resolved on</p>
                    <p class="text-sm font-medium">{{ gap.resolvedAt }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Days to resolution</p>
                    <p class="text-sm font-medium text-emerald-600">{{ gap.daysOpen }} days</p>
                </div>
                <div v-if="gap.resolutionNote" class="sm:col-span-2">
                    <p class="text-xs text-muted-foreground">Resolution note</p>
                    <p class="mt-0.5 text-sm leading-relaxed">{{ gap.resolutionNote }}</p>
                </div>
            </div>
        </Card>

        <!-- No-resolution nudge -->
        <div v-else class="rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
            <span class="font-medium">Gap is still open.</span>
            {{ gap.daysOpen }} days since identification.
            <span v-if="gap.timeline"> Planned timeline: {{ gap.timeline }}.</span>
        </div>

    </main>
</template>
