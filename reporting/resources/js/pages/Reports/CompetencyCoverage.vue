<script setup>
import Card from '../../components/ui/Card.vue';
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import InsightsPanel from '../../components/InsightsPanel.vue';
import Pagination from '../../components/ui/Pagination.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, Download, ListChecks } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    view: { type: String, default: 'journeys' },
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    menteeOptions: { type: Array, default: () => [] },
    evaluatorOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stale: { type: Number, default: 2 },
    currentUserId: { type: String, default: null },
});

const selects = computed(() => [
    {
        key: 'tool_id',
        label: 'Tool',
        placeholder: 'All tools',
        options: props.tools.map((t) => ({ value: String(t.id), label: t.label })),
    },
    {
        key: 'district_id',
        label: 'District',
        placeholder: 'All districts',
        options: props.districts.map((d) => ({ value: String(d.id), label: d.name })),
    },
    {
        key: 'facility_id',
        label: 'Facility',
        placeholder: 'All facilities',
        options: props.facilities.map((f) => ({ value: String(f.id), label: f.name })),
    },
    {
        key: 'mentee_id',
        label: 'Mentee',
        placeholder: 'All mentees',
        options: props.menteeOptions.map((m) => ({ value: String(m.id), label: m.name })),
    },
    {
        key: 'evaluator_id',
        label: 'Evaluator',
        placeholder: 'Any evaluator',
        options: [
            ...(props.currentUserId ? [{ value: 'me', label: 'Journeys I have evaluated' }] : []),
            ...props.evaluatorOptions.map((e) => ({ value: String(e.id), label: e.name })),
        ],
    },
    {
        key: 'status',
        label: 'Journeys',
        placeholder: 'In progress',
        options: [
            { value: 'competent', label: 'Competent' },
            { value: 'all', label: 'All journeys' },
        ],
    },
    {
        key: 'stale',
        label: 'Not evaluated recently',
        placeholder: 'For 2+ sessions',
        options: [1, 3, 4, 5].map((n) => ({ value: String(n), label: `For ${n}+ session${n === 1 ? '' : 's'}` })),
    },
    {
        key: 'advanced',
        label: 'Advanced items',
        placeholder: 'Leave out',
        options: [{ value: '1', label: 'Include' }],
    },
]);

function setView(view) {
    const params = { ...props.filters, view: view === 'journeys' ? undefined : view };
    delete params.page;
    router.get('/competency-coverage', Object.fromEntries(Object.entries(params).filter(([, v]) => v != null && v !== '')), {
        preserveState: true,
        replace: true,
    });
}

const exportUrl = computed(() => {
    const params = new URLSearchParams({ ...props.filters, export: 'csv' });
    return `/competency-coverage?${params.toString()}`;
});

const expanded = ref(new Set());
function toggle(groupId) {
    const next = new Set(expanded.value);
    next.has(groupId) ? next.delete(groupId) : next.add(groupId);
    expanded.value = next;
}

const kindLabel = (item) =>
    ({ never: 'Never evaluated', only_na: 'Only N/A so far', stale: `Not evaluated for ${item.sessionsSince} session${item.sessionsSince === 1 ? '' : 's'}` })[item.kind];

const kindClass = (kind) =>
    kind === 'stale' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800';

const heatmapUrl = (groupId) => `/journey-heatmap?group_id=${encodeURIComponent(groupId)}`;
</script>

<template>
    <Head title="Competency Coverage" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <InsightsPanel
            summary="Shows the competencies each journey has missed, so an evaluator can see what still needs to be covered, including what a previous evaluator did not reach."
            :points="[
                'Mentors evaluate only what they observe, so some competencies go unevaluated. A journey cannot reach competency until every required item’s latest score is 4 or 5, so a never-evaluated item blocks it.',
                'Never evaluated: no score yet in the journey, or rated only N/A so far.',
                `Not evaluated recently: the latest score is below 4 and the item has not been rated for ${stale} or more sessions. Achieved items are never flagged; they do not need re-rating.`,
                'Coverage counts every evaluator’s sessions in a journey. Filter by evaluator to see journeys someone has worked on, including ones they have handed over.',
            ]"
        />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-normal">Competency Coverage</h1>
                <p class="text-sm text-muted-foreground">Competencies not yet evaluated, or not evaluated recently, per journey and per competency.</p>
            </div>
            <a
                :href="exportUrl"
                class="inline-flex h-9 items-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-medium hover:bg-muted"
            >
                <Download class="size-4" />
                Export CSV
            </a>
        </div>

        <FilterBar :filters="filters" :selects="selects" url="/competency-coverage" />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Journeys" :value="String(summary.journeys ?? 0)" helper="Matching the filters" />
            <MetricCard
                label="With never-evaluated items"
                :value="String(summary.journeysWithNever ?? 0)"
                helper="Cannot reach competency until covered"
            />
            <MetricCard label="Never-evaluated items" :value="String(summary.neverItems ?? 0)" helper="Across these journeys" />
            <MetricCard
                label="Not evaluated recently"
                :value="String(summary.staleItems ?? 0)"
                :helper="`Below 4 and not rated for ${stale}+ sessions`"
            />
        </section>

        <div class="flex gap-1 rounded-lg bg-muted p-1 text-sm font-medium" role="group" aria-label="Report view">
            <button
                type="button"
                class="flex-1 rounded-md px-3 py-2"
                :class="view === 'journeys' ? 'bg-card shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                :aria-pressed="view === 'journeys'"
                @click="setView('journeys')"
            >
                By journey
            </button>
            <button
                type="button"
                class="flex-1 rounded-md px-3 py-2"
                :class="view === 'competencies' ? 'bg-card shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                :aria-pressed="view === 'competencies'"
                @click="setView('competencies')"
            >
                By competency
            </button>
        </div>

        <!-- By journey -->
        <Card v-if="view === 'journeys'">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium">Facility / District</th>
                            <th class="px-4 py-3 font-medium">Last evaluated</th>
                            <th class="px-4 py-3 font-medium text-right">Required achieved</th>
                            <th class="px-4 py-3 font-medium text-right">Never evaluated</th>
                            <th class="px-4 py-3 font-medium text-right">Not recent</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">
                                <ListChecks class="mx-auto mb-2 size-8 opacity-40" />
                                No journeys match the current filters.
                            </td>
                        </tr>
                        <template v-for="j in rows" :key="j.groupId">
                            <tr class="border-t hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium">{{ j.mentee }}</td>
                                <td class="px-4 py-3">{{ j.tool }}</td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ j.facility ?? '—' }}<span v-if="j.district" class="block text-xs">{{ j.district }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ j.lastSessionDate }}
                                    <span class="block text-xs text-muted-foreground">by {{ j.lastEvaluator ?? 'unknown' }} · {{ j.totalSessions }} session{{ j.totalSessions === 1 ? '' : 's' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ j.requiredAchieved }} of {{ j.requiredItems }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="j.neverCount > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">{{ j.neverCount }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="j.staleCount > 0 ? 'font-semibold text-amber-600' : 'text-muted-foreground'">{{ j.staleCount }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button
                                        v-if="j.missed?.length"
                                        type="button"
                                        class="mr-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                        :aria-expanded="expanded.has(j.groupId)"
                                        @click="toggle(j.groupId)"
                                    >
                                        {{ expanded.has(j.groupId) ? 'Hide' : 'Show' }} items
                                        <ChevronDown class="size-4 transition-transform" :class="expanded.has(j.groupId) ? 'rotate-180' : ''" />
                                    </button>
                                    <TableLink :href="heatmapUrl(j.groupId)" tooltip="Open the journey heatmap">Heatmap →</TableLink>
                                </td>
                            </tr>
                            <tr v-if="expanded.has(j.groupId)" class="bg-muted/20">
                                <td colspan="8" class="px-4 py-3">
                                    <ul class="grid gap-2 md:grid-cols-2">
                                        <li v-for="item in j.missed" :key="item.number" class="flex items-start gap-3 rounded-md border bg-card px-3 py-2">
                                            <span class="min-w-10 shrink-0 rounded bg-muted px-1.5 py-0.5 text-center text-xs font-bold">{{ item.number }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block leading-snug">{{ item.title }}</span>
                                                <span class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="kindClass(item.kind)">{{ kindLabel(item) }}</span>
                                                    <span v-if="item.latestScore != null" class="text-muted-foreground">latest score {{ item.latestScore }} on {{ item.lastEvaluatedDate }}</span>
                                                    <span v-if="item.isAdvanced" class="rounded-full bg-gray-700 px-2 py-0.5 font-semibold text-white">Advanced</span>
                                                </span>
                                            </span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="border-t px-4 py-3">
                <Pagination :meta="meta" />
            </div>
        </Card>

        <!-- By competency -->
        <Card v-else>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Competency</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium text-right">Journeys</th>
                            <th class="px-4 py-3 font-medium text-right">Never evaluated</th>
                            <th class="px-4 py-3 font-medium text-right">Not recent</th>
                            <th class="px-4 py-3 font-medium text-right">Achieved</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">No competencies match the current filters.</td>
                        </tr>
                        <tr v-for="c in rows" :key="c.itemId" class="border-t hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <span class="flex items-start gap-3">
                                    <span class="min-w-10 shrink-0 rounded bg-muted px-1.5 py-0.5 text-center text-xs font-bold">{{ c.number }}</span>
                                    <span class="leading-snug">
                                        {{ c.title }}
                                        <span v-if="c.isAdvanced" class="ml-1 rounded-full bg-gray-700 px-2 py-0.5 text-xs font-semibold text-white">Advanced</span>
                                    </span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ c.tool }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ c.journeys }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="c.neverCount > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">{{ c.neverCount }}</span>
                                <span class="block text-xs text-muted-foreground">{{ c.neverPct }}%</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="c.staleCount > 0 ? 'font-semibold text-amber-600' : 'text-muted-foreground'">{{ c.staleCount }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-700">{{ c.achievedCount }}</td>
                            <td class="px-4 py-3 text-right">
                                <TableLink :href="`/tool-analysis/items/${c.itemId}`" tooltip="Open the item analysis">Analysis →</TableLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t px-4 py-3">
                <Pagination :meta="meta" />
            </div>
        </Card>
    </main>
</template>
