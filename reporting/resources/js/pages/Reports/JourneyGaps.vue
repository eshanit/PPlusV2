<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, MapPin } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journey: { type: Object, required: true },
    gaps: { type: Array, default: () => [] },
});

const statusVariant = (status) =>
    ({ fully_competent: 'success', basic_competent: 'secondary', in_progress: 'warning' }[status] ?? 'outline');

const statusLabel = (status) =>
    ({ fully_competent: 'Fully Competent', basic_competent: 'Basic Competent', in_progress: 'In Progress' }[status] ?? '—');

const domainConfig = {
    knowledge: { label: 'Knowledge', cls: 'bg-blue-100 text-blue-700' },
    critical_reasoning: { label: 'Critical Reasoning', cls: 'bg-purple-100 text-purple-700' },
    clinical_skills: { label: 'Clinical Skills', cls: 'bg-emerald-100 text-emerald-700' },
    communication: { label: 'Communication', cls: 'bg-amber-100 text-amber-700' },
    attitude: { label: 'Attitude', cls: 'bg-rose-100 text-rose-700' },
};

const supervisionLabel = (level) =>
    ({
        intensive_mentorship: 'Intensive Mentorship',
        ongoing_mentorship: 'Ongoing Mentorship',
        independent_practice: 'Independent Practice',
    }[level] ?? level ?? '—');
</script>

<template>
    <Head :title="`Gaps — ${journey.menteeName}`" />

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/journey-status" class="flex items-center gap-1 hover:text-foreground">
                <ArrowLeft class="size-4" />
                Journey Status
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <Link
                :href="`/journey-sessions?group_id=${encodeURIComponent(journey.groupId)}`"
                class="hover:text-foreground"
            >
                {{ journey.menteeName }}
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <span class="text-foreground">Open Gaps</span>
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

                <div class="flex gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold tabular-nums">{{ journey.totalSessions }}</p>
                        <p class="text-xs text-muted-foreground">Total sessions</p>
                    </div>
                    <div v-if="journey.openGaps > 0">
                        <p class="text-2xl font-bold tabular-nums text-orange-600">{{ journey.openGaps }}</p>
                        <p class="text-xs text-muted-foreground">Open gaps</p>
                    </div>
                    <div v-if="journey.resolvedGaps > 0">
                        <p class="text-2xl font-bold tabular-nums text-emerald-600">{{ journey.resolvedGaps }}</p>
                        <p class="text-xs text-muted-foreground">Resolved gaps</p>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Gaps table -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">All Gaps</h2>
                <p class="text-xs text-muted-foreground">Click any gap to view detailed analysis and reporting.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Domains</th>
                            <th class="px-4 py-3 font-medium">Identified</th>
                            <th class="px-4 py-3 font-medium">Supervision</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="gaps.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                No gaps recorded for this journey.
                            </td>
                        </tr>
                        <tr
                            v-for="(g, i) in gaps"
                            :key="g.id"
                            class="border-t transition-colors hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ i + 1 }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <p class="line-clamp-2 text-sm">{{ g.description }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="d in g.domains"
                                        :key="d"
                                        :class="domainConfig[d]?.cls ?? 'bg-muted text-muted-foreground'"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    >
                                        {{ domainConfig[d]?.label ?? d }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ g.identifiedAt }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ g.supervisionLevel ? supervisionLabel(g.supervisionLevel) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="g.isResolved"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                >
                                    Resolved
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700"
                                >
                                    Open
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <TableLink
                                    :href="`/gap-report/${g.id}`"
                                    tooltip="View gap analysis and reporting"
                                >
                                    View analysis →
                                </TableLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

    </main>
</template>
