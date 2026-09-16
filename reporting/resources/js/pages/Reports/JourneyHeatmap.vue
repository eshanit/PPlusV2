<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ChevronRight, MapPin, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journey: { type: Object, default: null },
    sessions: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
});

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

// Group rows by category
const groupedRows = computed(() => {
    const map = new Map();
    for (const row of props.rows) {
        if (!map.has(row.category)) {
            map.set(row.category, []);
        }
        map.get(row.category).push(row);
    }
    return [...map.entries()].map(([category, rows]) => ({ category, rows }));
});

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

function avgScoreClass(score) {
    if (score == null) return 'text-muted-foreground';
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600';
    return 'text-red-600 font-semibold';
}
</script>

<template>
    <Head :title="journey ? `Heatmap — ${journey.mentee}` : 'Journey Heatmap'" />

    <main class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">

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
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Link href="/journey-status" class="flex items-center gap-1 hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Journey Status
                </Link>
                <ChevronRight class="size-3 opacity-50" />
                <span class="text-foreground">{{ journey.mentee }} — {{ journey.tool }}</span>
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
                    </div>
                </div>
            </Card>

            <!-- Comparison controls -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
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
                <span class="flex items-center gap-1">
                    <span class="inline-block size-4 rounded bg-emerald-100" />
                    Score 4–5 (competent)
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block size-4 rounded bg-amber-100" />
                    Score 3 (developing)
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block size-4 rounded bg-red-100" />
                    Score 1–2 (gap)
                </span>
                <span class="flex items-center gap-1">
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
            </div>

            <!-- Heatmap table -->
            <Card class="overflow-hidden">
                <div class="overflow-x-auto">
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
                                        class="mx-auto mb-1 flex h-4 w-7 items-center rounded-full transition-colors"
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
                                            class="inline-flex h-7 w-9 items-center justify-center rounded text-xs font-semibold tabular-nums"
                                            :class="cellClass(cell)"
                                        >
                                            {{ cellLabel(cell) }}
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
