<script setup>
import ApexChart from '../../components/ui/ApexChart.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Award, ChevronDown, ChevronRight, FileText, MapPin, Printer, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    session: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    counsellingItems: { type: Array, default: () => [] },
    distribution: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    prevSession: { type: Object, default: null },
    openGaps: { type: Number, default: 0 },
    latestSupervisionLevel: { type: String, default: null },
    trajectory: { type: Array, default: () => [] },
    journeyStatus: { type: Object, default: null },
});

// ── print ────────────────────────────────────────────────────────────────────
let savedBuckets = null;

function handleBeforePrint() {
    savedBuckets = new Set(openBuckets.value);
    openBuckets.value = new Set(['na', 1, 2, 3, 4, 5]);
}

function handleAfterPrint() {
    if (savedBuckets !== null) {
        openBuckets.value = savedBuckets;
        savedBuckets = null;
    }
}

onMounted(() => {
    window.addEventListener('beforeprint', handleBeforePrint);
    window.addEventListener('afterprint', handleAfterPrint);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeprint', handleBeforePrint);
    window.removeEventListener('afterprint', handleAfterPrint);
});

function printReport() {
    window.print();
}

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

// "Prev"/Δ compare to whichever session has session_number - 1, which counts
// same-day rounds too — so "prev" can mean an earlier pass in this same
// visit, not a separate day's visit. Those read very differently for an M&E
// officer, so label which one it actually is rather than just saying "prev".
const prevComparisonLabel = computed(() => {
    if (!props.prevSession) return null;
    return props.prevSession.sameDay
        ? `vs Round ${props.prevSession.dayRoundNumber} (earlier today)`
        : `vs visit on ${props.prevSession.date}`;
});

const prevComparisonShort = computed(() => {
    if (!props.prevSession) return 'Prev';
    return props.prevSession.sameDay ? `R${props.prevSession.dayRoundNumber}` : 'Prev';
});

const SUPERVISION_LABELS = {
    intensive_mentorship: 'Intensive Mentorship',
    ongoing_mentorship: 'Ongoing Mentorship',
    independent_practice: 'Independent Practice',
};
const supervisionLabel = (level) => SUPERVISION_LABELS[level] ?? level ?? '—';

// Exact wording from the PEN-Plus Mentorship Tool's scoring rubric (mentee
// row) — shown as score badge tooltips, matching the journey heatmap.
const SCORE_RUBRIC = {
    1: 'Does not demonstrate competency',
    2: 'Demonstrates basic competency',
    3: 'Demonstrates satisfactory competency',
    4: 'Demonstrates advanced competency',
    5: 'Demonstrates exceptional competency',
};
const scoreTooltip = (score) => (score == null ? 'N/A — the competency cannot be evaluated' : SCORE_RUBRIC[score]);

// Per the tool's phase-graduation rule, only basic (non-advanced)
// competencies count toward the 70% threshold — explained on hover since a
// percentage alone doesn't say why the bar moves between phases.
const phaseTargetTooltip = computed(() => {
    const target = props.stats.target;
    if (target == null) return null;
    return (
        `Per the PEN-Plus Mentorship Tool, basic (non-advanced) competencies need to score ${target}+ during the ` +
        `${phaseLabel(props.session.phase)} phase (3+ for Initial Intensive, 4+ for Ongoing/Supervision). ` +
        `${props.stats.competencyGap} of ${props.stats.basicScoredCount} basic items scored in this session are below that bar.`
    );
});

// ── change since last round/visit ───────────────────────────────────────────
// Combines the two item sets (tool + counselling) since both already carry a
// delta vs whichever session came before — same source data the item table
// shows, just summarised so a regression or improvement doesn't require
// scanning every row.
const changeSinceLast = computed(() => {
    const all = [...props.items, ...props.counsellingItems].filter((i) => i.delta != null);
    const regressed = all.filter((i) => i.delta < 0).sort((a, b) => a.delta - b.delta);
    const improved = all.filter((i) => i.delta > 0).sort((a, b) => b.delta - a.delta);
    return { regressed, improved, evaluatedCount: all.length };
});

// ── session insights ─────────────────────────────────────────────────────────
// One-line takeaway before anything else: how this specific visit went, and
// which way it's trending. Same score bands already used to colour the "At
// Phase Target" tile, so the wording and the colour on screen always agree.
const sessionVerdict = computed(() => {
    const pct = props.stats.pctAtCompetency ?? 0;
    const band = pct >= 80
        ? { text: 'Strong session', color: 'text-emerald-600' }
        : pct >= 50
            ? { text: 'Mixed session', color: 'text-amber-600' }
            : { text: 'Weak session', color: 'text-red-600' };

    const target = props.stats.target ?? 4;
    const detail = `${pct}% of basic items scoring ${target}+`;

    const trendDelta = props.stats.vsPrevSession;
    let trend = null;
    if (trendDelta != null && prevComparisonLabel.value) {
        if (trendDelta > 0) trend = `improving ${prevComparisonLabel.value}`;
        else if (trendDelta < 0) trend = `declining ${prevComparisonLabel.value}`;
        else trend = `unchanged ${prevComparisonLabel.value}`;
    }

    return { ...band, detail, trend };
});

// Where this visit sits among every scored session in the journey — flags an
// unusually good or bad visit that the raw number alone might not stand out.
const sessionRank = computed(() => {
    const scored = props.trajectory.filter((s) => s.avgScore != null);
    const current = scored.find((s) => s.isCurrent);
    if (!current || scored.length < 2) return null;

    const sorted = [...scored].sort((a, b) => b.avgScore - a.avgScore);
    const rank = sorted.findIndex((s) => s.sessionId === current.sessionId) + 1;

    return { rank, total: scored.length, isBest: rank === 1, isWorst: rank === scored.length };
});

const ordinal = (n) => {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return `${n}${s[(v - 20) % 10] ?? s[v] ?? s[0]}`;
};

// Critical (high-risk) items scored 1-2 this session — a gap on a routine
// item and a gap on a critical one carry very different urgency.
const criticalGaps = computed(() => props.items.filter((i) => i.isCritical && i.score != null && i.score <= 2));

// Items scored 5 (exceptional) this visit — balance against the gaps above.
const sessionStrengths = computed(() =>
    [...props.items, ...props.counsellingItems].filter((i) => i.score === 5),
);

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

// Round-aware category label — only multi-round days get a "(Rn)" suffix, so
// journeys with one visit per day keep the plain "Session N" look. Annotation
// x-values below must be built from this same function, not a separate
// string, since ApexChart matches annotations to categories by exact text.
const categoryLabel = (s) => (s.dayRoundCount > 1 ? `Session ${s.session} (R${s.dayRoundNumber})` : `Session ${s.session}`);

const currentSessionAnnotation = computed(() => {
    const current = props.trajectory.find((s) => s.isCurrent);
    if (!current) return {};
    return {
        xaxis: [
            {
                x: categoryLabel(current),
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
    const target = props.trajectory.find((s) => s.session === props.journeyStatus?.sessionsToBasic);
    if (!target) return currentSessionAnnotation.value;
    return {
        xaxis: [
            ...(currentSessionAnnotation.value.xaxis ?? []),
            {
                x: categoryLabel(target),
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
        categories: props.trajectory.map(categoryLabel),
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

        <!-- Back link + print button -->
        <div class="flex items-center justify-between print:hidden">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Link
                    :href="`/score-trajectory?tool_id=&group_id=${session.evaluationGroupId}`"
                    class="flex items-center gap-1 hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Back to journey
                </Link>
                <ChevronRight class="size-3 opacity-50" />
                <span class="text-foreground">
                    Session {{ session.sessionNumber }}
                    <template v-if="session.dayRoundCount > 1">(Round {{ session.dayRoundNumber }} of {{ session.dayRoundCount }})</template>
                </span>
            </div>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-sm font-medium text-foreground shadow-sm hover:bg-muted/60"
                @click="printReport"
            >
                <Printer class="size-4" />
                Print / Save PDF
            </button>
        </div>

        <!-- Print-only header -->
        <div class="hidden print:block">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">PEN-Plus · Session Report</p>
            <h1 class="text-2xl font-bold">{{ session.menteeName }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ session.toolLabel }} · {{ session.date }} · Session {{ session.sessionNumber }} of {{ session.totalSessions }}
                <template v-if="session.dayRoundCount > 1">· Round {{ session.dayRoundNumber }} of {{ session.dayRoundCount }} today</template>
            </p>
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
                        <Badge v-if="latestSupervisionLevel" variant="outline">
                            {{ supervisionLabel(latestSupervisionLevel) }} recommended
                        </Badge>
                        <Badge v-if="openGaps > 0" variant="warning">{{ openGaps }} open gap{{ openGaps !== 1 ? 's' : '' }}</Badge>
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
                    <Badge v-if="session.dayRoundCount > 1" variant="outline" class="mt-1">
                        Round {{ session.dayRoundNumber }} of {{ session.dayRoundCount }} today
                    </Badge>
                </div>
            </div>

            <div v-if="session.notes" class="mt-3 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                <span class="font-medium text-foreground">Notes:</span> {{ session.notes }}
            </div>
        </Card>

        <!-- Insights -->
        <Card class="space-y-3 p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p class="flex items-baseline gap-2">
                    <span :class="['text-base font-semibold', sessionVerdict.color]">{{ sessionVerdict.text }}</span>
                    <span class="text-xs text-muted-foreground">{{ sessionVerdict.detail }}</span>
                </p>
                <p v-if="sessionVerdict.trend" class="flex items-center gap-1 text-xs text-muted-foreground">
                    <component :is="stats.vsPrevSession > 0 ? TrendingUp : TrendingDown" class="size-3.5" />
                    {{ sessionVerdict.trend }}
                </p>
            </div>

            <p v-if="sessionRank" class="text-xs text-muted-foreground">
                <template v-if="sessionRank.isBest">Best-scoring session so far in this journey</template>
                <template v-else-if="sessionRank.isWorst">Lowest-scoring session so far in this journey — worth a closer look</template>
                <template v-else>{{ ordinal(sessionRank.rank) }} of {{ sessionRank.total }} scored sessions in this journey, by average</template>
            </p>

            <div v-if="criticalGaps.length || sessionStrengths.length" class="grid gap-3 sm:grid-cols-2">
                <div v-if="criticalGaps.length" class="rounded-lg border border-orange-200 bg-orange-50/50 p-3">
                    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-orange-700">
                        <AlertTriangle class="size-3.5" />
                        Critical items scoring low
                    </p>
                    <ul class="space-y-1 text-xs">
                        <li v-for="i in criticalGaps" :key="i.itemId" class="flex items-center justify-between gap-2">
                            <span class="truncate">
                                <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                {{ i.title }}
                            </span>
                            <span class="shrink-0 font-semibold text-red-600">{{ i.score }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="sessionStrengths.length" class="rounded-lg border p-3">
                    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                        <Award class="size-3.5" />
                        Exceptional this session
                    </p>
                    <ul class="space-y-1 text-xs">
                        <li v-for="i in sessionStrengths.slice(0, 5)" :key="i.itemId" class="flex items-center justify-between gap-2">
                            <span class="truncate">
                                <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                {{ i.title }}
                            </span>
                            <span class="shrink-0 font-semibold text-emerald-600">5</span>
                        </li>
                        <li v-if="sessionStrengths.length > 5" class="text-muted-foreground">+{{ sessionStrengths.length - 5 }} more</li>
                    </ul>
                </div>
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
                        <p v-if="stats.vsPrevSession != null" :class="['text-xs', deltaColor(stats.vsPrevSession)]" :title="prevComparisonLabel">
                            {{ deltaLabel(stats.vsPrevSession) }} {{ prevComparisonLabel }}
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

                    <div class="rounded-lg bg-muted/50 p-3" :title="phaseTargetTooltip">
                        <p class="text-xs text-muted-foreground">At Phase Target (≥{{ stats.target ?? 4 }})</p>
                        <p :class="['text-2xl font-bold tabular-nums', stats.pctAtCompetency >= 80 ? 'text-emerald-600' : stats.pctAtCompetency >= 50 ? 'text-amber-600' : 'text-red-600']">
                            {{ stats.pctAtCompetency }}%
                        </p>
                        <p class="text-xs text-muted-foreground/60">basic items, {{ phaseLabel(session.phase) }} phase</p>
                    </div>

                    <div class="col-span-2 rounded-lg bg-muted/50 p-3" :title="phaseTargetTooltip">
                        <p class="text-xs text-muted-foreground">Competency Gap</p>
                        <p class="text-lg font-semibold text-foreground">
                            <span :class="stats.competencyGap > 0 ? 'text-red-600' : 'text-emerald-600'">
                                {{ stats.competencyGap }}
                            </span>
                            <span class="text-sm font-normal text-muted-foreground">
                                of {{ stats.basicScoredCount }} basic item{{ stats.basicScoredCount !== 1 ? 's' : '' }} below the ≥{{ stats.target ?? 4 }} phase target
                            </span>
                        </p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Change since last round/visit -->
        <Card v-if="prevSession" class="p-4">
            <h2 class="mb-1 text-sm font-semibold">Change {{ prevComparisonLabel }}</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                {{ changeSinceLast.regressed.length }} regressed, {{ changeSinceLast.improved.length }} improved, of
                {{ changeSinceLast.evaluatedCount }} items scored both times.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border p-3">
                    <p class="mb-2 flex items-center gap-1 text-xs font-semibold text-red-700">Regressed</p>
                    <ul v-if="changeSinceLast.regressed.length" class="space-y-1 text-xs">
                        <li v-for="i in changeSinceLast.regressed.slice(0, 5)" :key="i.itemId" class="flex items-center justify-between gap-2">
                            <span class="truncate">
                                <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                {{ i.title }}
                            </span>
                            <span class="shrink-0 font-semibold text-red-600">{{ i.prevScore }} → {{ i.score }}</span>
                        </li>
                        <li v-if="changeSinceLast.regressed.length > 5" class="text-muted-foreground">
                            +{{ changeSinceLast.regressed.length - 5 }} more
                        </li>
                    </ul>
                    <p v-else class="text-xs text-muted-foreground">No items dropped.</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="mb-2 flex items-center gap-1 text-xs font-semibold text-emerald-700">Improved</p>
                    <ul v-if="changeSinceLast.improved.length" class="space-y-1 text-xs">
                        <li v-for="i in changeSinceLast.improved.slice(0, 5)" :key="i.itemId" class="flex items-center justify-between gap-2">
                            <span class="truncate">
                                <span class="font-mono text-[10px] text-muted-foreground">{{ i.number }}</span>
                                {{ i.title }}
                            </span>
                            <span class="shrink-0 font-semibold text-emerald-600">{{ i.prevScore }} → {{ i.score }}</span>
                        </li>
                        <li v-if="changeSinceLast.improved.length > 5" class="text-muted-foreground">
                            +{{ changeSinceLast.improved.length - 5 }} more
                        </li>
                    </ul>
                    <p v-else class="text-xs text-muted-foreground">No items improved.</p>
                </div>
            </div>
        </Card>

        <!-- Items by score (accordion) -->
        <Card>
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Items by Score</h2>
            </div>
            <div class="divide-y">
                <div v-for="b in buckets" :key="b.key">
                    <button
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-muted/30 print:pointer-events-none"
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

                    <div v-if="openBuckets.has(b.key) && itemsByBucket[b.key]?.length > 0" class="accordion-panel bg-muted/20 px-4 pb-3 pt-1">
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
                                    title="Advanced (grey) competency — not required for Basic Competent status, only for Fully Competent. Still scored and reported, just not part of the 70% phase-advancement threshold."
                                >
                                    Advanced
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="openBuckets.has(b.key) && b.count === 0" class="accordion-panel bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                        No items in this bucket.
                    </div>
                </div>
            </div>
        </Card>

        <!-- Full item score table -->
        <Card class="page-break-before">
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">All Items — {{ session.toolLabel }}</h2>
                <p v-if="prevComparisonLabel" class="mt-0.5 text-xs text-muted-foreground">
                    Prev / Δ columns compare {{ prevComparisonLabel }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Item</th>
                            <th class="px-3 py-3 font-medium text-center">Score</th>
                            <th class="px-3 py-3 font-medium text-center" :title="prevComparisonLabel">{{ prevComparisonShort }}</th>
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
                                    :title="scoreTooltip(item.score)"
                                >
                                    {{ item.score }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/50" title="N/A — the competency cannot be evaluated">—</span>
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
                                    title="Advanced (grey) competency — not required for Basic Competent status, only for Fully Competent. Still scored and reported, just not part of the 70% phase-advancement threshold."
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
                            <th class="px-3 py-3 font-medium text-center" :title="prevComparisonLabel">{{ prevComparisonShort }}</th>
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
                                    :title="scoreTooltip(item.score)"
                                >
                                    {{ item.score }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground/50" title="N/A — the competency cannot be evaluated">—</span>
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
        <Card v-if="trajectory.length > 1" class="p-4 print-hide-chart">
            <h2 class="mb-1 text-base font-semibold">Journey Trajectory</h2>
            <p class="mb-3 text-xs text-muted-foreground">Average score across all sessions in this journey. Current session highlighted.</p>
            <ApexChart type="line" :series="trajectorySeries" :options="trajectoryOptions" :height="220" />
        </Card>

    </main>
</template>

<style>
@media print {
    @page {
        margin: 1.5cm;
        size: A4 portrait;
    }

    /* Hide AppLayout sidebar and mobile header */
    aside,
    header {
        display: none !important;
    }

    /* Make the content area full width */
    .flex-1 {
        display: block !important;
        width: 100% !important;
    }

    /* Remove card shadows and use simple borders */
    .shadow,
    .shadow-sm,
    .shadow-md {
        box-shadow: none !important;
    }

    /* Keep borders visible */
    [class*='border'] {
        border-color: #d1d5db !important;
    }

    /* Background colours that browsers strip — force them back for score badges */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Avoid page breaks inside cards and table rows */
    .card,
    tr {
        break-inside: avoid;
    }

    /* Force a page break before the full item table */
    .page-break-before {
        break-before: page;
    }

    /* Ensure accordion content is always visible (JS opens all before print,
       but this is a safety net for any that weren't v-if rendered yet) */
    .accordion-panel {
        display: block !important;
    }

    /* Hide the trajectory chart — SVG charts can mis-scale on print */
    .print-hide-chart {
        display: none !important;
    }
}
</style>
