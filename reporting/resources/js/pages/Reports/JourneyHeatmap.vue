<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ArrowUpRight, Award, ChevronRight, Download, Eye, Info, MapPin, Printer, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journey: { type: Object, default: null },
    sessions: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    openGapDomainCounts: { type: Object, default: () => ({}) },
    latestSupervisionLevel: { type: String, default: null },
});

// Print setup lives on this page only (removed on unmount) so it never
// leaks landscape/color-adjust rules onto other reports' print output.
// The sidebar/navbar themselves are hidden globally via AppLayout's own
// print:hidden classes.
const PRINT_STYLE_ID = 'journey-heatmap-print-style';

onMounted(() => {
    const style = document.createElement('style');
    style.id = PRINT_STYLE_ID;
    style.textContent = `
        @media print {
            @page { size: landscape; margin: 10mm; }
            .journey-heatmap-report, .journey-heatmap-report * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .journey-heatmap-report .heatmap-scroll {
                overflow: visible !important;
            }
        }
    `;
    document.head.appendChild(style);
});

onUnmounted(() => {
    document.getElementById(PRINT_STYLE_ID)?.remove();
});

function printReport() {
    window.print();
}

// Server-rendered PDF (headless Chrome via Browsershot) — always the full,
// all-sessions-included view, independent of the on-screen round toggles.
const pdfUrl = computed(() => `/journey-heatmap/pdf?group_id=${encodeURIComponent(props.journey?.groupId ?? '')}`);

// Every session/round starts included in the comparison. Toggling one off
// excludes it from every average below (the per-item row averages and the
// day-level deltas), recomputed entirely client-side since every raw score
// is already on the page — no server round-trip needed to re-run the compare.
const enabledSessionIds = ref(new Set(props.sessions.map((s) => s.id)));

function toggleSession(id) {
    const next = new Set(enabledSessionIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    enabledSessionIds.value = next;
}

function resetToggles() {
    enabledSessionIds.value = new Set(props.sessions.map((s) => s.id));
}

const round2 = (n) => Math.round(n * 100) / 100;

// A session's own average across every item in this tool (mirrors
// v_session_averages), used to build the day-level comparisons below.
function sessionAvg(sessionId) {
    const idx = props.sessions.findIndex((s) => s.id === sessionId);
    if (idx === -1) return null;
    const scores = props.rows
        .map((row) => row.cells[idx])
        .filter((cell) => cell.present && cell.score !== null)
        .map((cell) => cell.score);
    return scores.length > 0 ? round2(scores.reduce((a, b) => a + b, 0) / scores.length) : null;
}

// A row's average across only the currently-enabled sessions.
function rowAvg(row) {
    const scores = row.cells
        .filter((cell, idx) => enabledSessionIds.value.has(props.sessions[idx]?.id) && cell.present && cell.score !== null)
        .map((cell) => cell.score);
    return scores.length > 0 ? round2(scores.reduce((a, b) => a + b, 0) / scores.length) : null;
}

// Sessions are already ordered so same-day rounds are contiguous — group them
// into day columns, then recompute each day's stats from only the enabled
// sessions: the within-day delta is first-enabled-round vs last-enabled-round
// for that day, and the day-over-day delta compares against the nearest
// earlier day that still has at least one enabled session (so disabling every
// other round reduces this to exactly "round N of day X vs round M of day Y").
const dayColumns = computed(() => {
    const groups = [];
    for (const s of props.sessions) {
        const last = groups[groups.length - 1];
        if (last && last.date === s.date) {
            last.sessions.push(s);
        } else {
            groups.push({ date: s.date, sessions: [s] });
        }
    }

    let prevAvg = null;
    let prevDate = null;
    for (const g of groups) {
        const enabled = g.sessions.filter((s) => enabledSessionIds.value.has(s.id));
        const avgs = enabled.map((s) => sessionAvg(s.id)).filter((a) => a !== null);
        const firstAvg = avgs[0] ?? null;
        const lastAvg = avgs[avgs.length - 1] ?? null;

        g.enabledCount = enabled.length;
        g.intraDayDelta = enabled.length > 1 && firstAvg !== null && lastAvg !== null ? round2(lastAvg - firstAvg) : null;
        g.dayOverDayDelta = firstAvg !== null && prevAvg !== null ? round2(firstAvg - prevAvg) : null;
        g.prevDate = prevDate;

        if (lastAvg !== null) {
            prevAvg = lastAvg;
            prevDate = g.date;
        }
    }
    return groups;
});

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

// Per-item regression flags, using the same day/round segmentation as the
// header deltas above. Within a day with more than one enabled round, the
// day's last enabled round is flagged if it fell versus the day's first
// enabled round. Across days, a day's first enabled round is flagged if it
// fell versus the nearest earlier day's last enabled round (mirrors
// dayOverDayDelta — same "skip fully-excluded days" behaviour). Returns a
// Map of sessionId → reason string for the flagged cell in this row.
function regressedCells(row) {
    const flags = new Map();
    const cellFor = (sessionId) => {
        const idx = props.sessions.findIndex((s) => s.id === sessionId);
        return idx === -1 ? null : row.cells[idx];
    };

    let prevLastSession = null;
    for (const group of dayColumns.value) {
        const enabled = group.sessions.filter((s) => enabledSessionIds.value.has(s.id));
        if (enabled.length === 0) continue;

        const firstSession = enabled[0];
        const lastSession = enabled[enabled.length - 1];
        const firstCell = cellFor(firstSession.id);
        const lastCell = cellFor(lastSession.id);

        if (
            enabled.length > 1 &&
            firstCell?.present && firstCell.score !== null &&
            lastCell?.present && lastCell.score !== null &&
            lastCell.score < firstCell.score
        ) {
            flags.set(lastSession.id, `Regressed within ${group.date}: was ${firstCell.score}, now ${lastCell.score}`);
        }

        if (prevLastSession) {
            const prevCell = cellFor(prevLastSession.id);
            if (
                prevCell?.present && prevCell.score !== null &&
                firstCell?.present && firstCell.score !== null &&
                firstCell.score < prevCell.score
            ) {
                flags.set(firstSession.id, `Regressed vs ${prevLastSession.date}: was ${prevCell.score}, now ${firstCell.score}`);
            }
        }

        prevLastSession = lastSession;
    }

    return flags;
}

// Group rows by category, with regression flags attached per row so the
// template doesn't recompute them once per cell.
const groupedRows = computed(() => {
    const map = new Map();
    for (const row of props.rows) {
        if (!map.has(row.category)) {
            map.set(row.category, []);
        }
        map.get(row.category).push({ ...row, regressions: regressedCells(row) });
    }
    return [...map.entries()].map(([category, rows]) => ({ category, rows }));
});

// ---- Insights -------------------------------------------------------
// Everything below is derived from the same enabled-session window as the
// table (toggling a round off updates the insights alongside the heatmap).

const enabledSessionsOrdered = computed(() => props.sessions.filter((s) => enabledSessionIds.value.has(s.id)));

// A row's scores across only the enabled sessions, in chronological order,
// skipping rounds where the item wasn't scored (N/A or not in that session).
function observedScores(row) {
    return props.sessions
        .map((s, idx) => ({ id: s.id, score: row.cells[idx]?.present ? row.cells[idx].score : null }))
        .filter((e) => enabledSessionIds.value.has(e.id) && e.score !== null);
}

const itemInsights = computed(() =>
    props.rows.map((row) => {
        const obs = observedScores(row);
        const first = obs.length > 0 ? obs[0].score : null;
        const last = obs.length > 0 ? obs[obs.length - 1].score : null;
        return {
            row,
            obs,
            avg: rowAvg(row),
            first,
            last,
            delta: obs.length > 1 ? round2(last - first) : null,
            regressions: regressedCells(row),
        };
    }),
);

// Mean of each row's average within a category — a quick strengths/weaknesses
// read at the competency-area level, above the noise of individual items.
const categoryStats = computed(() => {
    const map = new Map();
    for (const { row, avg } of itemInsights.value) {
        if (avg === null) continue;
        if (!map.has(row.category)) map.set(row.category, []);
        map.get(row.category).push(avg);
    }
    return [...map.entries()]
        .map(([category, avgs]) => ({ category, avg: round2(avgs.reduce((a, b) => a + b, 0) / avgs.length) }))
        .sort((a, b) => b.avg - a.avg);
});

const strongCategories = computed(() => categoryStats.value.filter((c) => c.avg >= 4).slice(0, 3));

const strongItems = computed(() =>
    itemInsights.value
        .filter((i) => i.avg !== null && i.avg >= 4 && i.regressions.size === 0)
        .sort((a, b) => b.avg - a.avg)
        .slice(0, 5),
);

// Currently scoring low on the latest enabled round — critical items first.
const needsAttention = computed(() =>
    itemInsights.value
        .filter((i) => i.last !== null && i.last <= 2)
        .sort((a, b) => Number(b.row.isCritical) - Number(a.row.isCritical) || a.last - b.last)
        .slice(0, 6),
);

const mostImproved = computed(() =>
    itemInsights.value
        .filter((i) => i.delta !== null && i.delta >= 1)
        .sort((a, b) => b.delta - a.delta)
        .slice(0, 5),
);

// Flagged via the same regression logic as the table cells above.
const regressedItems = computed(() =>
    itemInsights.value
        .filter((i) => i.regressions.size > 0)
        .sort((a, b) => b.regressions.size - a.regressions.size || (a.delta ?? 0) - (b.delta ?? 0))
        .slice(0, 5),
);

// Scored on more than one included round and never once rose above a "gap"
// score — distinct from a one-off regression, this is a stuck item.
const persistentGaps = computed(() =>
    itemInsights.value
        .filter((i) => i.obs.length > 1 && i.obs.every((o) => o.score <= 2))
        .sort((a, b) => Number(b.row.isCritical) - Number(a.row.isCritical) || (a.avg ?? 0) - (b.avg ?? 0))
        .slice(0, 5),
);

const overallTrend = computed(() => {
    const first = enabledSessionsOrdered.value[0];
    const last = enabledSessionsOrdered.value[enabledSessionsOrdered.value.length - 1];
    if (!first || !last) return { firstAvg: null, lastAvg: null, delta: null };

    const firstAvg = sessionAvg(first.id);
    const lastAvg = first.id === last.id ? firstAvg : sessionAvg(last.id);
    const delta = first.id !== last.id && firstAvg !== null && lastAvg !== null ? round2(lastAvg - firstAvg) : null;

    return { firstAvg, lastAvg, delta };
});

// ---- Phase advancement, supervision & gap domains --------------------
// Straight from the PEN-Plus Mentorship Tool's own phase-graduation rule:
// Initial Intensive → Ongoing needs ≥70% of basic (non-advanced) competencies
// scoring ≥3 "on repeated observations"; Ongoing → Supervision needs ≥70%
// scoring ≥4. Mentor/autonomy scoring was dropped per product decision, so
// mentee score stands in for "autonomy" here. Supervision is the terminal
// phase — there's no further threshold to show once a mentee is there.

const PHASE_LABELS = { initial_intensive: 'Initial Intensive', ongoing: 'Ongoing', supervision: 'Supervision' };
const PHASE_TARGET_SCORE = { initial_intensive: 3, ongoing: 4 };
const PHASE_NEXT = { initial_intensive: 'ongoing', ongoing: 'supervision' };

const phaseLabel = (phase) => PHASE_LABELS[phase] ?? phase ?? '—';

const phaseAdvancement = computed(() => {
    const currentPhase = enabledSessionsOrdered.value[enabledSessionsOrdered.value.length - 1]?.phase ?? null;

    if (currentPhase === 'supervision') {
        return { state: 'final', currentPhase };
    }
    if (!currentPhase || !(currentPhase in PHASE_TARGET_SCORE)) {
        return { state: 'unknown', currentPhase };
    }

    const target = PHASE_TARGET_SCORE[currentPhase];
    const basicEvaluated = itemInsights.value.filter((i) => !i.row.isAdvanced && i.last !== null);
    const atTarget = basicEvaluated.filter((i) => i.last >= target);
    // Below-target items, closest to clearing the bar first — these are the
    // quickest wins for the next mentorship visit.
    const belowTarget = basicEvaluated.filter((i) => i.last < target).sort((a, b) => b.last - a.last);
    const percent = basicEvaluated.length > 0 ? Math.round((atTarget.length / basicEvaluated.length) * 100) : null;
    const itemsNeeded = basicEvaluated.length > 0 ? Math.max(0, Math.ceil(basicEvaluated.length * 0.7) - atTarget.length) : null;

    return {
        state: 'progressing',
        currentPhase,
        target,
        percent,
        evaluatedCount: basicEvaluated.length,
        atTargetCount: atTarget.length,
        itemsNeeded,
        belowTargetItems: belowTarget,
        nextPhase: PHASE_NEXT[currentPhase],
        ready: percent !== null && percent >= 70,
    };
});

// The "Phase progress" tile's info tooltip — an M&E officer needs a verdict
// and what to act on next, not just the raw rule the percentage is based on.
const phaseAdvancementTooltip = computed(() => {
    const a = phaseAdvancement.value;

    if (a.state === 'final') {
        return 'In Supervision — the terminal phase. Focus on random audits and quality oversight rather than direct teaching; log any new gaps found.';
    }

    if (a.state === 'unknown') {
        return 'No mentorship phase was recorded on the latest included session, so phase-advancement progress can\'t be shown. Record a phase (Initial Intensive / Ongoing / Supervision) on the next session.';
    }

    const currentLabel = phaseLabel(a.currentPhase);
    const nextLabel = phaseLabel(a.nextPhase);

    if (a.ready) {
        return (
            `Ready to advance from ${currentLabel} to ${nextLabel}: ${a.atTargetCount} of ${a.evaluatedCount} ` +
            `basic competencies (${a.percent}%) are scoring ${a.target}+, above the tool's 70% bar.`
        );
    }

    const lines = [
        `Not yet ready to advance from ${currentLabel} to ${nextLabel}: ${a.atTargetCount} of ${a.evaluatedCount} ` +
        `basic competencies (${a.percent}%) score ${a.target}+ — needs ${a.itemsNeeded} more to clear the tool's 70% bar.`,
    ];

    if (a.belowTargetItems.length > 0) {
        const preview = a.belowTargetItems.slice(0, 4).map((i) => `${i.row.number} (${i.last})`).join(', ');
        const more = a.belowTargetItems.length > 4 ? `, +${a.belowTargetItems.length - 4} more` : '';
        lines.push(`Focus next visit on: ${preview}${more}.`);
    }

    return lines.join('\n');
});

const SUPERVISION_LABELS = {
    intensive_mentorship: 'Intensive Mentorship',
    ongoing_mentorship: 'Ongoing Mentorship',
    independent_practice: 'Independent Practice',
};
const supervisionLabel = (level) => SUPERVISION_LABELS[level] ?? level ?? '—';

// Matches the color coding already used for gap domains on the Journey Gaps
// and Gap List pages, for visual consistency across reports.
const DOMAIN_META = {
    knowledge: { label: 'Knowledge', cls: 'bg-blue-100 text-blue-700' },
    critical_reasoning: { label: 'Critical Reasoning', cls: 'bg-purple-100 text-purple-700' },
    clinical_skills: { label: 'Clinical Skills', cls: 'bg-emerald-100 text-emerald-700' },
    communication: { label: 'Communication', cls: 'bg-amber-100 text-amber-700' },
    attitude: { label: 'Attitude', cls: 'bg-rose-100 text-rose-700' },
};
const domainLabel = (domain) => DOMAIN_META[domain]?.label ?? domain;
const domainClass = (domain) => DOMAIN_META[domain]?.cls ?? 'bg-muted text-muted-foreground';

const gapDomainEntries = computed(() =>
    Object.entries(props.openGapDomainCounts ?? {})
        .map(([domain, count]) => ({ domain, count }))
        .sort((a, b) => b.count - a.count),
);

// Cell colour based on score value and present flag
function cellClass(cell) {
    if (!cell.present) return 'bg-muted/20';
    if (cell.score === null) return 'bg-muted/40 text-muted-foreground';
    if (cell.score >= 4) return 'bg-emerald-100 text-emerald-800';
    if (cell.score === 3) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
}

function cellLabel(cell) {
    if (!cell.present) return '';
    if (cell.score === null) return 'N/A';
    return String(cell.score);
}

// Exact wording from the PEN-Plus Mentorship Tool's scoring rubric (mentee
// row) — shown as cell tooltips so mentors get the full definition, not just
// the 1-5 shorthand.
const SCORE_RUBRIC = {
    1: 'Does not demonstrate competency',
    2: 'Demonstrates basic competency',
    3: 'Demonstrates satisfactory competency',
    4: 'Demonstrates advanced competency',
    5: 'Demonstrates exceptional competency',
};

// A regression reason takes priority over the rubric text when both apply.
function cellTooltip(row, idx) {
    const sessionId = props.sessions[idx]?.id;
    const regression = row.regressions.get(sessionId);
    if (regression) return regression;

    const cell = row.cells[idx];
    if (!cell.present) return 'Not evaluated in this session';
    if (cell.score === null) return 'N/A — the competency cannot be evaluated';

    return SCORE_RUBRIC[cell.score] ?? null;
}

function avgScoreClass(score) {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600 font-semibold';
}
</script>

<template>
    <Head :title="journey ? `Heatmap — ${journey.mentee}` : 'Journey Heatmap'" />

    <main class="journey-heatmap-report mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8 print:px-0 print:py-0">

        <!-- No group_id state -->
        <div
            v-if="!journey"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center"
        >
            <p class="text-sm font-medium text-muted-foreground">No journey selected. Open this page via a journey link.</p>
            <Link href="/journey-status" class="mt-3 text-sm text-primary underline underline-offset-2">
                Go to Journey Status
            </Link>
        </div>

        <template v-else>
            <!-- Breadcrumb -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground print:hidden">
                    <Link href="/journey-status" class="flex items-center gap-1 hover:text-foreground">
                        <ArrowLeft class="size-4" />
                        Journey Status
                    </Link>
                    <ChevronRight class="size-3 opacity-50" />
                    <span class="text-foreground">{{ journey.mentee }} — {{ journey.tool }}</span>
                </div>
                <div class="ml-auto flex items-center gap-2 print:hidden">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/50"
                        @click="printReport"
                    >
                        <Printer class="size-3.5" />
                        Print
                    </button>
                    <a
                        :href="pdfUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-1.5 rounded-md border bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90"
                    >
                        <Download class="size-3.5" />
                        Download PDF
                    </a>
                </div>
            </div>

            <!-- Journey header -->
            <Card class="p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-lg font-semibold">{{ journey.mentee }}</p>
                        <p class="text-sm text-muted-foreground">{{ journey.tool }}</p>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span v-if="journey.facility" class="flex items-center gap-1 text-sm text-muted-foreground">
                                <MapPin class="size-3 opacity-60" />
                                {{ journey.facility }}
                            </span>
                            <span v-if="journey.district" class="text-sm text-muted-foreground/60">
                                {{ journey.district }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">Sessions</p>
                            <p class="text-2xl font-bold tabular-nums">{{ journey.totalSessions }}</p>
                        </div>
                        <Badge :variant="statusVariant(journey.status)">{{ statusLabel(journey.status) }}</Badge>
                        <Badge v-if="latestSupervisionLevel" variant="outline">
                            {{ supervisionLabel(latestSupervisionLevel) }} recommended
                        </Badge>
                    </div>
                </div>
            </Card>

            <!-- Insights -->
            <Card class="space-y-4 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Insights</h2>
                    <p class="text-[11px] text-muted-foreground">
                        Based on <span class="font-medium text-foreground">{{ enabledSessionsOrdered.length }}</span> of
                        {{ sessions.length }} sessions currently included
                    </p>
                </div>

                <!-- Overview stat strip -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <div class="rounded-md border p-3">
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Overall trend</p>
                        <div
                            v-if="overallTrend.delta !== null"
                            class="mt-1 flex items-center gap-1 text-lg font-bold"
                            :class="overallTrend.delta >= 0 ? 'text-emerald-600' : 'text-red-600'"
                        >
                            <component :is="overallTrend.delta >= 0 ? TrendingUp : TrendingDown" class="size-4" />
                            {{ overallTrend.delta >= 0 ? '+' : '' }}{{ overallTrend.delta }}
                        </div>
                        <p v-else class="mt-1 text-lg font-bold text-muted-foreground">—</p>
                        <p class="text-[10px] text-muted-foreground">{{ overallTrend.firstAvg ?? '—' }} → {{ overallTrend.lastAvg ?? '—' }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Open gaps</p>
                        <p class="mt-1 text-lg font-bold" :class="(journey.openGaps ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600'">
                            {{ journey.openGaps ?? 0 }}
                        </p>
                        <p class="text-[10px] text-muted-foreground">{{ journey.resolvedGaps ?? 0 }} resolved</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Regressions flagged</p>
                        <p class="mt-1 text-lg font-bold" :class="regressedItems.length > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ regressedItems.length }}
                        </p>
                        <p class="text-[10px] text-muted-foreground">items dropped vs. prior round</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Persistent gaps</p>
                        <p class="mt-1 text-lg font-bold" :class="persistentGaps.length > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ persistentGaps.length }}
                        </p>
                        <p class="text-[10px] text-muted-foreground">never scored above 2</p>
                    </div>
                    <div class="rounded-md border p-3" :title="phaseAdvancementTooltip">
                        <p class="flex items-center gap-1 text-[10px] uppercase tracking-wide text-muted-foreground">
                            Phase progress
                            <Info class="size-3 shrink-0 opacity-60" />
                        </p>
                        <template v-if="phaseAdvancement.state === 'progressing' && phaseAdvancement.percent !== null">
                            <p class="mt-1 text-lg font-bold" :class="phaseAdvancement.ready ? 'text-emerald-600' : 'text-amber-600'">
                                {{ phaseAdvancement.percent }}%
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                ≥{{ phaseAdvancement.target }} — need 70% for {{ phaseLabel(phaseAdvancement.nextPhase) }}
                            </p>
                        </template>
                        <template v-else-if="phaseAdvancement.state === 'final'">
                            <p class="mt-1 text-lg font-bold text-muted-foreground">—</p>
                            <p class="text-[10px] text-muted-foreground">Already in Supervision</p>
                        </template>
                        <template v-else>
                            <p class="mt-1 text-lg font-bold text-muted-foreground">—</p>
                            <p class="text-[10px] text-muted-foreground">Phase not recorded</p>
                        </template>
                    </div>
                </div>

                <!-- Open gap domains -->
                <div v-if="gapDomainEntries.length" class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-medium text-muted-foreground">Open gap domains:</span>
                    <span
                        v-for="d in gapDomainEntries"
                        :key="d.domain"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium"
                        :class="domainClass(d.domain)"
                    >
                        {{ domainLabel(d.domain) }} ({{ d.count }})
                    </span>
                </div>

                <!-- Narrative panels -->
                <div class="grid gap-3 lg:grid-cols-2">
                    <!-- Strengths -->
                    <div class="rounded-md border p-3">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                            <Award class="size-3.5" />
                            Strengths
                        </div>
                        <div v-if="strongCategories.length" class="mb-2 flex flex-wrap gap-1">
                            <Badge v-for="c in strongCategories" :key="c.category" variant="success" class="text-[10px]">
                                {{ c.category }} ({{ c.avg.toFixed(1) }})
                            </Badge>
                        </div>
                        <ul v-if="strongItems.length" class="space-y-1 text-xs">
                            <li v-for="i in strongItems" :key="i.row.id" class="flex items-center justify-between gap-2">
                                <span class="truncate">
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.row.number }}</span>
                                    {{ i.row.title }}
                                </span>
                                <span class="shrink-0 font-semibold text-emerald-600">{{ i.avg.toFixed(1) }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">Not enough consistently strong items yet.</p>
                    </div>

                    <!-- Needs attention -->
                    <div class="rounded-md border p-3">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-red-700">
                            <Eye class="size-3.5" />
                            Needs attention
                        </div>
                        <ul v-if="needsAttention.length" class="space-y-1 text-xs">
                            <li v-for="i in needsAttention" :key="i.row.id" class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1 truncate">
                                    <AlertTriangle v-if="i.row.isCritical" class="size-3 shrink-0 text-orange-500" />
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.row.number }}</span>
                                    {{ i.row.title }}
                                </span>
                                <span class="shrink-0 font-semibold text-red-600">{{ i.last }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">Nothing currently scoring low — nice work.</p>
                    </div>

                    <!-- Most improved -->
                    <div class="rounded-md border p-3">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                            <ArrowUpRight class="size-3.5" />
                            Most improved
                        </div>
                        <ul v-if="mostImproved.length" class="space-y-1 text-xs">
                            <li v-for="i in mostImproved" :key="i.row.id" class="flex items-center justify-between gap-2">
                                <span class="truncate">
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.row.number }}</span>
                                    {{ i.row.title }}
                                </span>
                                <span class="shrink-0 font-semibold text-emerald-600">{{ i.first }} → {{ i.last }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">No items improved by 1+ points yet across included rounds.</p>
                    </div>

                    <!-- Regressed or stuck -->
                    <div class="rounded-md border p-3">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-red-700">
                            <TrendingDown class="size-3.5" />
                            Regressed or stuck
                        </div>
                        <ul v-if="regressedItems.length || persistentGaps.length" class="space-y-1 text-xs">
                            <li
                                v-for="i in regressedItems"
                                :key="'r-' + i.row.id"
                                class="flex items-center justify-between gap-2"
                                :title="[...i.regressions.values()][0]"
                            >
                                <span class="truncate">
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.row.number }}</span>
                                    {{ i.row.title }}
                                </span>
                                <span class="shrink-0 font-semibold text-red-600">{{ i.first }} → {{ i.last }}</span>
                            </li>
                            <li
                                v-for="i in persistentGaps.filter((p) => !regressedItems.some((r) => r.row.id === p.row.id))"
                                :key="'g-' + i.row.id"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="truncate">
                                    <span class="font-mono text-[10px] text-muted-foreground">{{ i.row.number }}</span>
                                    {{ i.row.title }}
                                </span>
                                <Badge variant="outline" class="shrink-0 text-[9px]">stuck at {{ i.avg?.toFixed(1) }}</Badge>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">No regressions or persistent gaps detected.</p>
                    </div>
                </div>
            </Card>

            <!-- Comparison controls -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground print:hidden">
                <p>
                    Toggle sessions off to compare specific rounds — averages and day deltas below only include the
                    <span class="font-medium text-foreground">{{ enabledSessionIds.size }} of {{ sessions.length }}</span>
                    sessions still switched on.
                </p>
                <button
                    type="button"
                    class="rounded-md border px-2.5 py-1 font-medium text-foreground hover:bg-muted/50"
                    @click="resetToggles"
                >
                    Reset — include all
                </button>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                <span class="font-medium">Legend:</span>
                <span class="flex items-center gap-1" :title="`4 = ${SCORE_RUBRIC[4]} · 5 = ${SCORE_RUBRIC[5]}`">
                    <span class="inline-block size-4 rounded bg-emerald-100" />
                    Score 4–5 (competent)
                </span>
                <span class="flex items-center gap-1" :title="SCORE_RUBRIC[3]">
                    <span class="inline-block size-4 rounded bg-amber-100" />
                    Score 3 (developing)
                </span>
                <span class="flex items-center gap-1" :title="`1 = ${SCORE_RUBRIC[1]} · 2 = ${SCORE_RUBRIC[2]}`">
                    <span class="inline-block size-4 rounded bg-red-100" />
                    Score 1–2 (gap)
                </span>
                <span class="flex items-center gap-1" title="The competency cannot be evaluated">
                    <span class="inline-block size-4 rounded bg-muted/40 border" />
                    N/A
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block size-4 rounded bg-muted/20 border" />
                    Not in session
                </span>
                <span class="flex items-center gap-1 text-orange-500">
                    <AlertTriangle class="size-3" />
                    High-risk item
                </span>
                <span class="flex items-center gap-1 text-red-600">
                    <span class="inline-block size-4 rounded ring-2 ring-inset ring-red-500" />
                    Regressed vs. prior round
                </span>
            </div>

            <!-- Heatmap table -->
            <Card class="overflow-hidden print:border-none print:shadow-none">
                <div class="heatmap-scroll overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <!-- Day + session header rows -->
                        <thead class="border-b">
                            <!-- Day column groups, with day-over-day delta vs. the previous day's last round -->
                            <tr class="border-b border-dashed">
                                <th class="sticky left-0 z-10 bg-card"></th>
                                <th
                                    v-for="group in dayColumns"
                                    :key="group.date"
                                    :colspan="group.sessions.length"
                                    class="px-2 py-1.5 text-center text-[10px] font-normal text-muted-foreground"
                                    :class="{ 'opacity-30': group.enabledCount === 0 }"
                                >
                                    <div class="opacity-70">{{ group.date }}</div>
                                    <div v-if="group.enabledCount === 0" class="italic">excluded</div>
                                    <div
                                        v-else-if="group.dayOverDayDelta != null"
                                        class="inline-flex items-center gap-0.5 font-medium"
                                        :class="group.dayOverDayDelta >= 0 ? 'text-emerald-600' : 'text-red-600'"
                                        :title="`vs last round on ${group.prevDate}`"
                                    >
                                        <component :is="group.dayOverDayDelta >= 0 ? TrendingUp : TrendingDown" class="size-3" />
                                        {{ group.dayOverDayDelta >= 0 ? '+' : '' }}{{ group.dayOverDayDelta }}
                                    </div>
                                    <div
                                        v-if="group.sessions.length > 1 && group.intraDayDelta != null"
                                        class="text-[9px] opacity-70"
                                    >
                                        within day: {{ group.intraDayDelta >= 0 ? '+' : '' }}{{ group.intraDayDelta }}
                                    </div>
                                </th>
                                <th class="bg-card"></th>
                            </tr>
                            <tr>
                                <th class="sticky left-0 z-10 min-w-64 bg-card px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                    Competency Item
                                </th>
                                <th
                                    v-for="s in sessions"
                                    :key="s.id"
                                    class="min-w-16 px-2 py-3 text-center font-medium text-muted-foreground"
                                >
                                    <button
                                        type="button"
                                        class="mx-auto mb-1 flex h-4 w-7 items-center rounded-full transition-colors print:hidden"
                                        :class="enabledSessionIds.has(s.id) ? 'bg-primary' : 'bg-muted'"
                                        :title="enabledSessionIds.has(s.id) ? 'Included in comparison — click to exclude' : 'Excluded — click to include'"
                                        @click="toggleSession(s.id)"
                                    >
                                        <span
                                            class="block size-3 rounded-full bg-white shadow transition-transform"
                                            :class="enabledSessionIds.has(s.id) ? 'translate-x-3.5' : 'translate-x-0.5'"
                                        />
                                    </button>
                                    <div class="text-xs font-semibold" :class="{ 'opacity-40': !enabledSessionIds.has(s.id) }">S{{ s.number }}</div>
                                    <div v-if="s.dayRoundCount > 1" class="text-[10px] font-normal opacity-70">Round {{ s.dayRoundNumber }}</div>
                                </th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-muted-foreground">Avg</th>
                            </tr>
                        </thead>

                        <tbody>
                            <template v-for="group in groupedRows" :key="group.category">
                                <!-- Category separator row -->
                                <tr class="bg-muted/40">
                                    <td
                                        :colspan="sessions.length + 2"
                                        class="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        {{ group.category }}
                                    </td>
                                </tr>

                                <!-- Item rows -->
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.id"
                                    class="border-t hover:bg-muted/20"
                                >
                                    <!-- Item label (sticky) -->
                                    <td class="sticky left-0 z-10 bg-card px-4 py-2">
                                        <div class="flex items-start gap-1.5">
                                            <span class="shrink-0 font-mono text-[10px] text-muted-foreground">
                                                {{ row.number }}
                                            </span>
                                            <AlertTriangle
                                                v-if="row.isCritical"
                                                class="mt-px size-3 shrink-0 text-orange-500"
                                            />
                                            <span class="line-clamp-2 leading-tight">{{ row.title }}</span>
                                            <Badge
                                                v-if="row.isAdvanced"
                                                variant="outline"
                                                class="ml-auto shrink-0 text-[9px]"
                                            >
                                                Adv
                                            </Badge>
                                        </div>
                                    </td>

                                    <!-- Score cells -->
                                    <td
                                        v-for="(cell, idx) in row.cells"
                                        :key="idx"
                                        class="px-1 py-1 text-center"
                                        :class="{ 'opacity-30': !enabledSessionIds.has(sessions[idx]?.id) }"
                                    >
                                        <span
                                            class="relative inline-flex h-7 w-9 items-center justify-center rounded text-xs font-semibold tabular-nums"
                                            :class="[cellClass(cell), { 'ring-2 ring-inset ring-red-500': row.regressions.has(sessions[idx]?.id) }]"
                                            :title="cellTooltip(row, idx)"
                                        >
                                            {{ cellLabel(cell) }}
                                            <TrendingDown
                                                v-if="row.regressions.has(sessions[idx]?.id)"
                                                class="absolute -right-1.5 -top-1.5 size-3 rounded-full bg-white text-red-600 shadow"
                                            />
                                        </span>
                                    </td>

                                    <!-- Row average (of currently-enabled sessions only) -->
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        <span :class="avgScoreClass(rowAvg(row))">
                                            {{ rowAvg(row) != null ? rowAvg(row).toFixed(1) : '—' }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </main>
</template>
