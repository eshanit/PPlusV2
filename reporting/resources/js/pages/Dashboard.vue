<script setup>
import MetricCard from '../components/MetricCard.vue';
import Badge from '../components/ui/Badge.vue';
import Card from '../components/ui/Card.vue';
import AppLayout from '../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    CircleDot,
    ClipboardList,
    Clock3,
    Gauge,
    MapPin,
    ShieldCheck,
    TrendingUp,
    Users,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    summary: {
        type: Object,
        required: true,
    },
    toolProgress: {
        type: Array,
        default: () => [],
    },
    districtProgress: {
        type: Array,
        default: () => [],
    },
    recentCompletions: {
        type: Array,
        default: () => [],
    },
    activeJourneys: {
        type: Array,
        default: () => [],
    },
    gapSummary: {
        type: Object,
        required: true,
    },
});

const percent = (value) => `${trimNumber(value)}%`;
const whole = (value) => new Intl.NumberFormat().format(Number(value ?? 0));
const metric = (value, suffix = '') => (value === null || value === undefined ? '-' : `${trimNumber(value)}${suffix}`);

function trimNumber(value) {
    const number = Number(value ?? 0);

    return Number.isInteger(number) ? String(number) : number.toFixed(1);
}
</script>

<template>
    <Head title="Dashboard" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Evaluation journeys"
                    :value="whole(summary.totalJourneys)"
                    :helper="`${whole(summary.activeJourneys)} still in progress`"
                >
                    <template #icon>
                        <Users class="size-5" />
                    </template>
                </MetricCard>

                <MetricCard
                    label="Basic competence"
                    :value="percent(summary.basicCompletionRate)"
                    :helper="`${whole(summary.basicComplete)} journeys complete`"
                >
                    <template #icon>
                        <CheckCircle2 class="size-5" />
                    </template>
                </MetricCard>

                <MetricCard
                    label="Avg sessions to competence"
                    :value="metric(summary.averageSessionsToBasic)"
                    helper="Based on completed journeys"
                >
                    <template #icon>
                        <Gauge class="size-5" />
                    </template>
                </MetricCard>

                <MetricCard
                    label="Open gaps"
                    :value="whole(summary.openGaps)"
                    :helper="`${whole(gapSummary.coveredNow)} covered now, ${whole(gapSummary.coveringLater)} later`"
                >
                    <template #icon>
                        <ClipboardList class="size-5" />
                    </template>
                </MetricCard>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <div class="flex flex-col gap-1 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold tracking-normal">Competence by tool</h2>
                            <p class="text-sm text-muted-foreground">Basic competence closes a mentee-tool journey.</p>
                        </div>
                        <Badge variant="success">{{ whole(summary.fullComplete) }} fully competent</Badge>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="min-w-72 px-4 py-3 font-medium">Tool</th>
                                    <th class="px-4 py-3 font-medium">Journeys</th>
                                    <th class="px-4 py-3 font-medium">Basic</th>
                                    <th class="px-4 py-3 font-medium">Rate</th>
                                    <th class="px-4 py-3 font-medium">Avg sessions</th>
                                    <th class="px-4 py-3 font-medium">Avg days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="toolProgress.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                        No evaluation journeys have synced yet.
                                    </td>
                                </tr>
                                <tr
                                    v-for="tool in toolProgress"
                                    :key="tool.slug"
                                    class="border-t"
                                >
                                    <td class="px-4 py-3 font-medium">{{ tool.label }}</td>
                                    <td class="px-4 py-3">{{ whole(tool.totalJourneys) }}</td>
                                    <td class="px-4 py-3">{{ whole(tool.basicComplete) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex min-w-32 items-center gap-3">
                                            <div class="h-2 flex-1 rounded-full bg-muted">
                                                <div
                                                    class="h-2 rounded-full bg-primary"
                                                    :style="{ width: `${tool.completionRate}%` }"
                                                />
                                            </div>
                                            <span class="w-12 text-right tabular-nums">{{ percent(tool.completionRate) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ metric(tool.averageSessionsToBasic) }}</td>
                                    <td class="px-4 py-3">{{ metric(tool.averageDaysToBasic, 'd') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div class="space-y-6">
                    <Card class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold tracking-normal">Gap status</h2>
                                <p class="text-sm text-muted-foreground">{{ whole(gapSummary.total) }} total gaps</p>
                            </div>
                            <Activity class="size-5 text-primary" />
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Open</p>
                                <p class="mt-1 text-xl font-semibold">{{ whole(gapSummary.open) }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Resolved</p>
                                <p class="mt-1 text-xl font-semibold">{{ whole(gapSummary.resolved) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="level in gapSummary.supervisionLevels"
                                :key="level.label"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <span class="text-muted-foreground">{{ level.label }}</span>
                                <Badge variant="outline">{{ whole(level.total) }}</Badge>
                            </div>
                            <p
                                v-if="gapSummary.supervisionLevels.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No open supervision recommendations.
                            </p>
                        </div>
                    </Card>

                    <Card>
                        <div class="border-b p-4">
                            <h2 class="text-base font-semibold tracking-normal">Districts</h2>
                            <p class="text-sm text-muted-foreground">Highest-volume districts</p>
                        </div>

                        <div class="divide-y">
                            <div
                                v-if="districtProgress.length === 0"
                                class="p-4 text-sm text-muted-foreground"
                            >
                                No district data yet.
                            </div>
                            <div
                                v-for="district in districtProgress"
                                :key="district.id ?? district.name"
                                class="p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ district.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ whole(district.totalJourneys) }} journeys</p>
                                    </div>
                                    <Badge variant="secondary">{{ percent(district.completionRate) }}</Badge>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <div class="flex items-center justify-between border-b p-4">
                        <div>
                            <h2 class="text-base font-semibold tracking-normal">Recent completions</h2>
                            <p class="text-sm text-muted-foreground">Newest journeys reaching basic competence</p>
                        </div>
                        <ShieldCheck class="size-5 text-primary" />
                    </div>

                    <div class="divide-y">
                        <div
                            v-if="recentCompletions.length === 0"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No completed journeys yet.
                        </div>
                        <div
                            v-for="completion in recentCompletions"
                            :key="completion.evaluationGroupId"
                            class="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ completion.mentee }}</p>
                                <p class="mt-1 truncate text-sm text-muted-foreground">{{ completion.tool }}</p>
                                <p class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                    <MapPin class="size-3.5" />
                                    {{ completion.facility ?? completion.district ?? 'Unassigned' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 sm:flex-col sm:items-end">
                                <Badge variant="success">{{ completion.sessionsToBasic }} sessions</Badge>
                                <span class="text-xs text-muted-foreground">{{ completion.completedAt }}</span>
                            </div>
                        </div>
                    </div>
                </Card>

                <Card>
                    <div class="flex items-center justify-between border-b p-4">
                        <div>
                            <h2 class="text-base font-semibold tracking-normal">Active journeys</h2>
                            <p class="text-sm text-muted-foreground">Mentees not yet competent on the tool</p>
                        </div>
                        <TrendingUp class="size-5 text-primary" />
                    </div>

                    <div class="divide-y">
                        <div
                            v-if="activeJourneys.length === 0"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No active journeys.
                        </div>
                        <div
                            v-for="journey in activeJourneys"
                            :key="journey.evaluationGroupId"
                            class="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ journey.mentee }}</p>
                                <p class="mt-1 truncate text-sm text-muted-foreground">{{ journey.tool }}</p>
                                <p class="mt-1 truncate text-xs text-muted-foreground">{{ journey.facility ?? 'Unassigned facility' }}</p>
                            </div>
                            <div class="flex items-center gap-2 sm:flex-col sm:items-end">
                                <Badge variant="warning">
                                    <CircleDot class="mr-1 size-3" />
                                    {{ journey.totalSessions }} sessions
                                </Badge>
                                <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock3 class="size-3.5" />
                                    {{ journey.latestSessionDate }}
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>
            </section>
    </main>
</template>
