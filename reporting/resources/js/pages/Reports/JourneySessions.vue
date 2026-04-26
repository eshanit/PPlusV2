<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, MapPin } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journey: { type: Object, required: true },
    sessions: { type: Array, default: () => [] },
});

const statusVariant = (status) =>
    ({ fully_competent: 'success', basic_competent: 'secondary', in_progress: 'warning' }[status] ?? 'outline');

const statusLabel = (status) =>
    ({ fully_competent: 'Fully Competent', basic_competent: 'Basic Competent', in_progress: 'In Progress' }[status] ?? '—');

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

const isCompetencySession = (sessionNumber) =>
    props.journey.sessionsToBasic !== null &&
    sessionNumber === Number(props.journey.sessionsToBasic);
</script>

<template>
    <Head :title="`Sessions — ${journey.menteeName}`" />

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/journey-status" class="flex items-center gap-1 hover:text-foreground">
                <ArrowLeft class="size-4" />
                Journey Status
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <span class="text-foreground">{{ journey.menteeName }}</span>
        </div>

        <!-- Journey header -->
        <Card class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold">{{ journey.menteeName }}</h1>
                        <Badge :variant="statusVariant(journey.status)">
                            {{ statusLabel(journey.status) }}
                        </Badge>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground">{{ journey.toolLabel }}</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs text-muted-foreground">
                        <span>Evaluator: {{ journey.evaluatorName }}</span>
                        <span v-if="journey.facility">
                            <MapPin class="mr-0.5 inline size-3" />{{ journey.facility }}
                        </span>
                        <span v-if="journey.district">{{ journey.district }}</span>
                    </div>
                </div>

                <!-- Summary stats -->
                <div class="flex gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold tabular-nums">{{ journey.totalSessions }}</p>
                        <p class="text-xs text-muted-foreground">Total sessions</p>
                    </div>
                    <div v-if="journey.sessionsToBasic">
                        <p class="text-2xl font-bold tabular-nums text-emerald-600">{{ journey.sessionsToBasic }}</p>
                        <p class="text-xs text-muted-foreground">Sessions to competency</p>
                    </div>
                    <div v-if="journey.daysToBasic">
                        <p class="text-2xl font-bold tabular-nums">{{ journey.daysToBasic }}</p>
                        <p class="text-xs text-muted-foreground">Days to competency</p>
                    </div>
                    <div v-if="journey.openGaps > 0">
                        <p class="text-2xl font-bold tabular-nums text-red-600">{{ journey.openGaps }}</p>
                        <p class="text-xs text-muted-foreground">Open gaps</p>
                    </div>
                </div>
            </div>

            <div v-if="journey.basicCompetentAt" class="mt-3 flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                <span class="font-medium">Basic competency achieved:</span>
                <span>{{ journey.basicCompetentAt }}</span>
            </div>
        </Card>

        <!-- Sessions table -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">All Sessions</h2>
                <p class="text-xs text-muted-foreground">Click any session to view the full item-level report.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Phase</th>
                            <th class="px-4 py-3 font-medium text-right">Avg Score</th>
                            <th class="px-4 py-3 font-medium text-right">Scored</th>
                            <th class="px-4 py-3 font-medium text-right">N/A</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="sessions.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                No sessions found for this journey.
                            </td>
                        </tr>
                        <tr
                            v-for="s in sessions"
                            :key="s.sessionId"
                            class="border-t transition-colors hover:bg-muted/30"
                            :class="isCompetencySession(s.sessionNumber) ? 'bg-emerald-50' : ''"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-medium text-muted-foreground">{{ s.sessionNumber }}</span>
                                    <span
                                        v-if="isCompetencySession(s.sessionNumber)"
                                        class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                                    >
                                        Competent
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ s.date }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ phaseLabel(s.phase) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="scoreColor(s.avgScore)">
                                    {{ s.avgScore != null ? s.avgScore.toFixed(2) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ s.scoredItems ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ s.naItems ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/sessions/${s.sessionId}`"
                                    class="text-xs font-medium text-primary hover:underline"
                                >
                                    View report →
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

    </main>
</template>
