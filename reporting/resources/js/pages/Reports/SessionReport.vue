<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronRight, FileText, MapPin } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    session: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    counsellingItems: { type: Array, default: () => [] },
    distribution: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    trajectory: { type: Array, default: () => [] },
    journeyStatus: { type: Object, default: null },
});

// ── accordion state ─────────────────────────────────────────────────────────
const openBuckets = ref(new Set([1, 2, 3])); // low scores open by default

function toggleBucket(key) {
    if (openBuckets.value.has(key)) {
        openBuckets.value.delete(key);
    } else {
        openBuckets.value.add(key);
    }
}

// ── helpers ──────────────────────────────────────────────────────────────────
const phaseLabel = (phase) =>
    ({
        initial_intensive: 'Initial Intensive',
        ongoing: 'Ongoing',
        supervision: 'Supervision',
    }[phase] ?? phase ?? '—');

const statusVariant = (status) =>
    ({ fully_competent: 'success', basic_competent: 'secondary', in_progress: 'warning' }[status] ?? 'outline');

const statusLabel = (status) =>
    ({ fully_competent: 'Fully Competent', basic_competent: 'Basic Competent', in_progress: 'In Progress' }[status] ?? '—');

const journeyCompetencyStatus = computed(() => {
    if (!props.journeyStatus) return 'in_progress';
    if (props.journeyStatus.fullyCompetent) return 'fully_competent';
    if (props.journeyStatus.basicCompetent) return 'basic_competent';
    return 'in_progress';
});

const scoreColor = (score) => {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600';
};

const scoreBg = (score) => {
    if (score == null) return 'bg-muted text-muted-foreground';
    if (score >= 4) return 'bg-emerald-100 text-emerald-700';
    if (score >= 3) return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-700';
};

const deltaLabel = (delta) => {
    if (delta == null) return null;
    if (delta > 0) return `+${delta}`;
    if (delta < 0) return `${delta}`;
    return '0';
};

const deltaColor = (delta) => {
    if (delta == null) return 'text-muted-foreground/40';
    if (delta > 0) return 'text-emerald-600';
    if (delta < 0) return 'text-red-500';
    return 'text-muted-foreground';
};

// ── distribution bar ─────────────────────────────────────────────────────────
const totalItems = computed(() => props.items.length);

const buckets = computed(() => [
    { key: 'na', label: 'N/A', count: props.distribution.na ?? 0, color: 'bg-muted-foreground/30' },
    { key: 1, label: '1', count: props.distribution[1] ?? 0, color: 'bg-red-400' },
    { key: 2, label: '2', count: props.distribution[2] ?? 0, color: 'bg-orange-400' },
    { key: 3, label: '3', count: props.distribution[3] ?? 0, color: 'bg-amber-400' },
    { key: 4, label: '4', count: props.distribution[4] ?? 0, color: 'bg-emerald-400' },
    { key: 5, label: '5', count: props.distribution[5] ?? 0, color: 'bg-emerald-600' },
]);

const itemsByBucket = computed(() => {
    const groups = { na: [], 1: [], 2: [], 3: [], 4: [], 5: [] };
    for (const item of props.items) {
        const key = item.score == null ? 'na' : item.score;
        if (key in groups) groups[key].push(item);
    }
    return groups;
});

// ── trajectory chart ──────────────────────────────────────────────────────────
const trajectorySeries = computed(() => [
    {
        name: 'Avg Score',
        data: props.trajectory.map((s) => s.avgScore ?? null),
    },
]);

const currentSessionAnnotation = computed(() => {
    const current = props.trajectory.find((s) => s.isCurrent);
    if (!current) return {};
    return {
        xaxis: [
            {
                x: `Session ${current.session}`,
                borderColor: '#6366f1',
                label: {
                    text: 'This Session',
                    style: { color: '#fff', background: '#6366f1' },
                },
            },
        ],
    };
});

const basicCompetencyAnnotation = computed(() => {
    if (!props.journeyStatus?.sessionsToBasic) return {};
    return {
        xaxis: [
            ...(currentSessionAnnotation.value.xaxis ?? []),
            {
                x: `Session ${props.journeyStatus.sessionsToBasic}`,
                borderColor: '#22c55e',
                label: {
                    text: 'Basic Competent',
                    style: { color: '#fff', background: '#22c55e' },
                },
            },
        ],
    };
});

const trajectoryOptions = computed(() => ({
    xaxis: {
        categories: props.trajectory.map((s) => `Session ${s.session}`),
        title: { text: 'Session' },
    },
    yaxis: { min: 1, max: 5, tickAmount: 4, title: { text: 'Avg Score' } },
    markers: {
        size: props.trajectory.map((s) => (s.isCurrent ? 8 : 5)),
        fillOpacity: props.trajectory.map((s) => (s.isCurrent ? 1 : 0.7)),
    },
    stroke: { curve: 'smooth', width: 2 },
    annotations: props.journeyStatus?.sessionsToBasic
        ? basicCompetencyAnnotation.value
        : currentSessionAnnotation.value,
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (val) => (val != null ? val.toFixed(2) : '—') } },
}));
</script>

<template>
    <Head :title="`Session Report — ${session.menteeName}`" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Back link -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link
                :href="`/score-trajectory?tool_id=&group_id=${session.evaluationGroupId}`"
                class="flex items-center gap-1 hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Back to journey
            </Link>
            <ChevronRight class="size-3 opacity-50" />
            <span class="text-foreground">Session {{ session.sessionNumber }}</span>
        </div>

        <!-- Session header -->
        <Card class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <FileText class="size-5 text-muted-foreground" />
                        <h1 class="text-xl font-semibold">{{ session.menteeName }}</h1>
                        <Badge :variant="statusVariant(journeyCompetencyStatus)">
                            {{ statusLabel(journeyCompetencyStatus) }}
                        </Badge>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground">{{ session.toolLabel }}</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs text-muted-foreground">
                        <span>{{ session.date }}</span>
                        <span v-if="session.phase">{{ phaseLabel(session.phase) }}</span>
                        <span>Evaluator: {{ session.evaluatorName }}</span>
                        <span v-if="session.facility">
                            <MapPin class="mr-0.5 inline size-3" />{{ session.facility }}
                        </span>
                        <span v-if="session.district">{{ session.district }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold tabular-nums text-foreground">
                        {{ session.sessionNumber }}
                        <span class="text-lg font-normal text-muted-foreground">/{{ session.totalSessions }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">session in journey</p>
                </div>
            </div>

            <div v-if="session.notes" class="mt-3 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                <span class="font-medium text-foreground">Notes:</span> {{ session.notes }}
            </div>
        </Card>

        <!-- Distribution + Stats -->
        <div class="grid gap-4 md:grid-cols-2">

            <!-- Score distribution -->
            <Card class="p-4">
                <h2 class="mb-3 text-sm font-semibold">Score Distribution</h2>

                <!-- Stacked bar -->
                <div class="mb-4 flex h-6 w-full overflow-hidden rounded-full">
                    <div
                        v-for="b in buckets"
                        :key="b.key"
                        :class="[b.color, 'transition-all']"
                        :style="{ width: totalItems > 0 ? `${(b.count / totalItems) * 100}%` : '0%' }"
                        :title="`Score ${b.label}: ${b.count}`"
                    />
                </div>

                <!-- Legend table -->
                <div class="space-y-1.5">
                    <div v-for="b in buckets" :key="b.key" class="flex items-center gap-2 text-sm">
                        <span :class="[b.color, 'inline-block size-3 shrink-0 rounded-sm']" />
                        <span class="w-8 text-xs text-muted-foreground">
                            {{ b.key === 'na' ? 'N/A' : `Score ${b.label}` }}
                        </span>
                        <div class="flex-1">
                            <div class="h-1.5 w-full rounded-full bg-muted">
                                <div
                                    :class="[b.color, 'h-1.5 rounded-full transition-all']"
                                    :style="{ width: totalItems > 0 ? `${(b.count / totalItems) * 100}%` : '0%' }"
                                />
                            </div>
                        </div>
                        <span class="w-8 text-right tabular-nums text-xs font-medium">{{ b.count }}</span>
                        <span class="w-10 text-right tabular-nums text-xs text-muted-foreground">
                            {{ totalItems > 0 ? ((b.count / totalItems) * 100).toFixed(0) : 0 }}%
                        </span>
                    </div>
                </div>
            </Card>

            <!-- Performance stats -->
            <Card class="p-4">
                <h2 class="mb-3 text-sm font-semibold">Performance Statistics</h2>
                <div class="grid grid-cols-2 gap-3">

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground">Mean Score</p>
                        <p :class="['text-2xl font-bold tabular-nums', scoreColor(stats.mean)]">
                            {{ stats.mean != null ? stats.mean.toFixed(2) : '—' }}
                        </p>
                        <p v-if="stats.vsPrevSession != null" :class="['text-xs', deltaColor(stats.vsPrevSession)]">
                            {{ deltaLabel(stats.vsPrevSession) }} vs prev session
                        </p>
                        <p v-else class="text-xs text-muted-foreground/60">first session</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground">Median Score</p>
                        <p :class="['text-2xl font-bold tabular-nums', scoreColor(stats.median)]">
                            {{ stats.median != null ? stats.median : '—' }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground">Modal Score</p>
                        <p class="text-2xl font-bold tabular-nums text-foreground">
                            {{ stats.mode ?? '—' }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground">At Competency (≥4)</p>
                        <p :class="['text-2xl font-bold tabular-nums', stats.pctAtCompetency >= 80 ? 'text-emerald-600' : stats.pctAtCompetency >= 50 ? 'text-amber-600' : 'text-red-600']">
                            {{ stats.pctAtCompetency }}%
                        </p>
                    </div>

                    <div class="col-span-2 rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground">Competency Gap</p>
                        <p class="text-lg font-semibold text-foreground">
                            <span :class="stats.competencyGap > 0 ? 'text-red-600' : 'text-emerald-600'">
                                {{ stats.competencyGap }}
                            </span>
                            <span class="text-sm font-normal text-muted-foreground"> item{{ stats.competencyGap !== 1 ? 's' : '' }} below competency threshold</span>
                        </p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Items by score (accordion) -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Items by Score</h2>
            </div>
            <div class="divide-y">
                <div v-for="b in buckets" :key="b.key">
                    <button
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-muted/30"
                        @click="toggleBucket(b.key)"
                    >
                        <div class="flex items-center gap-2">
                            <span :class="[b.color, 'inline-block size-2.5 rounded-sm']" />
                            <span class="font-medium">
                                {{ b.key === 'na' ? 'Not Evaluated (N/A)' : `Score ${b.label}` }}
                            </span>
                            <span class="text-muted-foreground">({{ b.count }})</span>
                        </div>
                        <ChevronDown
                            class="size-4 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': openBuckets.has(b.key) }"
                        />
                    </button>

                    <div v-if="openBuckets.has(b.key) && itemsByBucket[b.key]?.length > 0" class="bg-muted/20 px-4 pb-3 pt-1">
                        <div class="space-y-1">
                            <div
                                v-for="item in itemsByBucket[b.key]"
                                :key="item.itemId"
                                class="flex items-start gap-3 rounded py-1.5 text-sm"
                            >
                                <span class="mt-0.5 shrink-0 font-mono text-xs font-medium text-muted-foreground">
                                    {{ item.number }}
                                </span>
                                <span class="flex-1 text-foreground">{{ item.title }}</span>
                                <span
                                    v-if="item.isAdvanced"
                                    class="shrink-0 rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700"
                                >
                                    Advanced
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="openBuckets.has(b.key) && b.count === 0" class="bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                        No items in this bucket.
                    </div>
                </div>
            </div>
        </Card>

        <!-- Full item score table -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">All Items — {{ session.toolLabel }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Item</th>
                            <th class="px-3 py-3 font-medium text-center">Score</th>
                            <th class="px-3 py-3 font-medium text-center">Prev</th>
                            <th class="px-3 py-3 font-medium text-center">Δ</th>
                            <th class="hidden px-3 py-3 font-medium sm:table-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No item scores recorded.</td>
                        </tr>
                        <tr
                            v-for="item in items"
                            :key="item.itemId"
                            class="border-t"
                            :class="item.score != null && item.score >= 4 ? 'bg-emerald-50/40' : ''"
                        >
                            <td class="px-4 py-2.5 font-mono text-xs font-medium text-muted-foreground">
                                {{ item.number }}
                            </td>
                            <td class="px-4 py-2.5 text-foreground">{{ item.title }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span
                                    v-if="item.score != null"
                                    :class="[scoreBg(item.score), 'inline-flex size-7 items-center justify-center rounded-full text-xs font-bold']"
                                >
                                    {{ item.score }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/50">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span v-if="item.prevScore != null" class="text-xs text-muted-foreground tabular-nums">
                                    {{ item.prevScore }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/30">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span :class="['text-xs font-medium tabular-nums', deltaColor(item.delta)]">
                                    {{ deltaLabel(item.delta) ?? '—' }}
                                </span>
                            </td>
                            <td class="hidden px-3 py-2.5 sm:table-cell">
                                <span
                                    v-if="item.isAdvanced"
                                    class="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700"
                                >
                                    Advanced
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Counselling scores -->
        <Card v-if="counsellingItems.length > 0">
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Counselling Competencies (DC1–DC9)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Item</th>
                            <th class="px-3 py-3 font-medium text-center">Score</th>
                            <th class="px-3 py-3 font-medium text-center">Prev</th>
                            <th class="px-3 py-3 font-medium text-center">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in counsellingItems"
                            :key="item.itemId"
                            class="border-t"
                            :class="item.score != null && item.score >= 4 ? 'bg-emerald-50/40' : ''"
                        >
                            <td class="px-4 py-2.5 font-mono text-xs font-medium text-muted-foreground">
                                {{ item.number }}
                            </td>
                            <td class="px-4 py-2.5 text-foreground">{{ item.title }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span
                                    v-if="item.score != null"
                                    :class="[scoreBg(item.score), 'inline-flex size-7 items-center justify-center rounded-full text-xs font-bold']"
                                >
                                    {{ item.score }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/50">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span v-if="item.prevScore != null" class="text-xs text-muted-foreground tabular-nums">
                                    {{ item.prevScore }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/30">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span :class="['text-xs font-medium tabular-nums', deltaColor(item.delta)]">
                                    {{ deltaLabel(item.delta) ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Journey trajectory -->
        <Card v-if="trajectory.length > 1" class="p-4">
            <h2 class="mb-1 text-base font-semibold">Journey Trajectory</h2>
            <p class="mb-3 text-xs text-muted-foreground">Average score across all sessions in this journey. Current session highlighted.</p>
            <ApexChart type="line" :series="trajectorySeries" :options="trajectoryOptions" :height="220" />
        </Card>

    </main>
</template>
