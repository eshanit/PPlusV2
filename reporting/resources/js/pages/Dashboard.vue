<script setup>
import MetricCard from '../components/MetricCard.vue';
import Badge from '../components/ui/Badge.vue';
import Card from '../components/ui/Card.vue';
import TableLink from '../components/ui/TableLink.vue';
import AppLayout from '../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    summary: { type: Object, required: true },
    toolProgress: { type: Array, default: () => [] },
    facilityProgress: { type: Array, default: () => [] },
    recentCompletions: { type: Array, default: () => [] },
    activeJourneys: { type: Array, default: () => [] },
    gapSummary: { type: Object, required: true },
});

const PREVIEW_LIMIT = 5;

const visibleTools = computed(() => props.toolProgress.slice(0, PREVIEW_LIMIT));
const visibleCompletions = computed(() => props.recentCompletions.slice(0, PREVIEW_LIMIT));
const visibleJourneys = computed(() => props.activeJourneys.slice(0, PREVIEW_LIMIT));

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

        <!-- Summary metric cards -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard
                label="Evaluation journeys"
                :value="whole(summary.totalJourneys)"
                :helper="`${whole(summary.activeJourneys)} still in progress`"
                href="/journey-status"
            >
                <template #icon><Users class="size-5" /></template>
            </MetricCard>

            <MetricCard
                label="Basic competence"
                :value="percent(summary.basicCompletionRate)"
                :helper="`${whole(summary.basicComplete)} journeys complete`"
                href="/journey-status?status=basic_competent"
            >
                <template #icon><CheckCircle2 class="size-5" /></template>
            </MetricCard>

            <MetricCard
                label="Avg sessions to competence"
                :value="metric(summary.averageSessionsToBasic)"
                helper="Based on completed journeys"
            >
                <template #icon><Gauge class="size-5" /></template>
            </MetricCard>

            <MetricCard
                label="Open gaps"
                :value="whole(summary.openGaps)"
                :helper="`${whole(gapSummary.coveredNow)} covered now, ${whole(gapSummary.coveringLater)} later`"
                href="/gap-overview"
            >
                <template #icon><ClipboardList class="size-5" /></template>
            </MetricCard>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">

            <!-- Competence by tool -->
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
                                v-for="tool in visibleTools"
                                :key="tool.slug"
                                class="border-t"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <TableLink
                                        :href="`/journey-status?tool_id=${tool.id}`"
                                        tooltip="View all journeys for this tool"
                                    >
                                        {{ tool.label }}
                                    </TableLink>
                                </td>
                                <td class="px-4 py-3">
                                    <TableLink
                                        :href="`/journey-status?tool_id=${tool.id}`"
                                        tooltip="View all journeys for this tool"
                                    >
                                        <span class="tabular-nums font-semibold">{{ whole(tool.totalJourneys) }}</span>
                                    </TableLink>
                                </td>
                                <td class="px-4 py-3">
                                    <TableLink
                                        :href="`/journey-status?tool_id=${tool.id}&status=basic_competent`"
                                        tooltip="View completed journeys for this tool"
                                    >
                                        <span class="tabular-nums font-semibold">{{ whole(tool.basicComplete) }}</span>
                                    </TableLink>
                                </td>
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
                                <td class="px-4 py-3 tabular-nums">{{ metric(tool.averageSessionsToBasic) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ metric(tool.averageDaysToBasic, 'd') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="toolProgress.length > PREVIEW_LIMIT" class="border-t px-4 py-3 text-center">
                    <Link href="/journey-status" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                        View all {{ toolProgress.length }} tools →
                    </Link>
                </div>
            </Card>

            <div class="space-y-6">

                <!-- Gap status -->
                <Card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold tracking-normal">Gap status</h2>
                            <p class="text-sm text-muted-foreground">{{ whole(gapSummary.total) }} total gaps</p>
                        </div>
                        <Activity class="size-5 text-primary" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <Link href="/gap-overview" class="group block">
                            <div class="rounded-md border p-3 transition-colors group-hover:border-primary/30 group-hover:bg-muted/30">
                                <p class="text-xs text-muted-foreground">Open</p>
                                <p class="mt-1 text-xl font-semibold text-orange-600">{{ whole(gapSummary.open) }}</p>
                            </div>
                        </Link>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Resolved</p>
                            <p class="mt-1 text-xl font-semibold text-emerald-600">{{ whole(gapSummary.resolved) }}</p>
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

                <!-- Facilities -->
                <Card>
                    <div class="border-b p-4">
                        <h2 class="text-base font-semibold tracking-normal">Facilities</h2>
                        <p class="text-sm text-muted-foreground">Highest-volume facilities</p>
                    </div>

                    <div class="divide-y">
                        <div v-if="facilityProgress.length === 0" class="p-4 text-sm text-muted-foreground">
                            No facility data yet.
                        </div>
                        <Link
                            v-for="facility in facilityProgress"
                            :key="facility.id ?? facility.name"
                            :href="`/journey-status?facility_id=${facility.id}`"
                            class="group block p-4 transition-colors hover:bg-muted/30"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium group-hover:text-primary">{{ facility.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ facility.district }} · {{ whole(facility.totalJourneys) }} journeys</p>
                                </div>
                                <Badge variant="secondary">{{ percent(facility.completionRate) }}</Badge>
                            </div>
                        </Link>
                    </div>
                </Card>

            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">

            <!-- Recent completions -->
            <Card>
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="text-base font-semibold tracking-normal">Recent completions</h2>
                        <p class="text-sm text-muted-foreground">Newest journeys reaching basic competence</p>
                    </div>
                    <ShieldCheck class="size-5 text-primary" />
                </div>

                <div class="divide-y">
                    <div v-if="recentCompletions.length === 0" class="p-4 text-sm text-muted-foreground">
                        No completed journeys yet.
                    </div>
                    <Link
                        v-for="completion in visibleCompletions"
                        :key="completion.evaluationGroupId"
                        :href="`/journey-sessions?group_id=${encodeURIComponent(completion.evaluationGroupId)}`"
                        class="grid gap-3 p-4 transition-colors hover:bg-muted/30 sm:grid-cols-[minmax(0,1fr)_auto]"
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
                    </Link>
                </div>
                <div v-if="recentCompletions.length > PREVIEW_LIMIT" class="border-t px-4 py-3 text-center">
                    <Link href="/journey-status?status=basic_competent" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                        View all {{ recentCompletions.length }} completions →
                    </Link>
                </div>
            </Card>

            <!-- Active journeys -->
            <Card>
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="text-base font-semibold tracking-normal">Active journeys</h2>
                        <p class="text-sm text-muted-foreground">Mentees not yet competent on the tool</p>
                    </div>
                    <TrendingUp class="size-5 text-primary" />
                </div>

                <div class="divide-y">
                    <div v-if="activeJourneys.length === 0" class="p-4 text-sm text-muted-foreground">
                        No active journeys.
                    </div>
                    <Link
                        v-for="journey in visibleJourneys"
                        :key="journey.evaluationGroupId"
                        :href="`/journey-sessions?group_id=${encodeURIComponent(journey.evaluationGroupId)}`"
                        class="grid gap-3 p-4 transition-colors hover:bg-muted/30 sm:grid-cols-[minmax(0,1fr)_auto]"
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
                    </Link>
                </div>
                <div v-if="activeJourneys.length > PREVIEW_LIMIT" class="border-t px-4 py-3 text-center">
                    <Link href="/journey-status?status=in_progress" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                        View all {{ activeJourneys.length }} active journeys →
                    </Link>
                </div>
            </Card>

        </section>
    </main>
</template>
