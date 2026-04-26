<script setup>
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import Pagination from '../../components/ui/Pagination.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journeys: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const statusOptions = [
    { value: 'in_progress', label: 'In Progress' },
    { value: 'basic_competent', label: 'Basic Competent' },
    { value: 'fully_competent', label: 'Fully Competent' },
];

const statusVariant = (status) => ({
    fully_competent: 'success',
    basic_competent: 'secondary',
    in_progress: 'warning',
}[status] ?? 'outline');

const statusLabel = (status) => ({
    fully_competent: 'Fully Competent',
    basic_competent: 'Basic Competent',
    in_progress: 'In Progress',
}[status] ?? status);

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};
</script>

<template>
    <Head title="Journey Status" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Journey Status</h1>
            <p class="text-sm text-muted-foreground">One row per mentee-tool journey. Filters apply instantly.</p>
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
                {
                    key: 'status',
                    label: 'Status',
                    placeholder: 'All statuses',
                    options: statusOptions,
                },
            ]"
        />

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="hidden px-4 py-3 font-medium sm:table-cell">District</th>
                            <th class="px-4 py-3 font-medium text-right">Sessions</th>
                            <th class="px-4 py-3 font-medium text-right">Score</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="hidden px-4 py-3 font-medium text-right xl:table-cell">Sessions→Competent</th>
                            <th class="hidden px-4 py-3 font-medium text-right xl:table-cell">Days→Competent</th>
                            <th class="px-4 py-3 font-medium text-right">Last Session</th>
                            <th class="px-4 py-3 font-medium text-right">Open Gaps</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="journeys.length === 0">
                            <td colspan="10" class="px-4 py-10 text-center text-muted-foreground">
                                No journeys match the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="j in journeys"
                            :key="j.evaluationGroupId"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">{{ j.mentee }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ j.tool }}</td>
                            <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                <span v-if="j.district" class="flex items-center gap-1">
                                    <MapPin class="size-3 shrink-0 opacity-60" />
                                    {{ j.district }}
                                </span>
                                <span v-else class="text-muted-foreground/50">—</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ j.totalSessions }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="scoreColor(j.latestAvgScore)" class="font-medium">
                                    {{ j.latestAvgScore != null ? j.latestAvgScore.toFixed(1) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(j.status)">{{ statusLabel(j.status) }}</Badge>
                            </td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground xl:table-cell">
                                {{ j.sessionsToBasic ?? '—' }}
                            </td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground xl:table-cell">
                                {{ j.daysToBasic != null ? j.daysToBasic + 'd' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-muted-foreground">{{ j.latestSessionDate ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <span
                                    v-if="j.openGaps > 0"
                                    class="font-semibold text-red-600"
                                >{{ j.openGaps }}</span>
                                <span v-else class="text-muted-foreground/50">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t px-4 py-3">
                <Pagination v-if="meta" :meta="meta" />
            </div>
        </Card>
    </main>
</template>
