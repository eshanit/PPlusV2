<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ChevronRight, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    journey: { type: Object, default: null },
    sessions: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
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
                        <!-- Session header row -->
                        <thead class="border-b">
                            <tr>
                                <th class="sticky left-0 z-10 min-w-64 bg-card px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                    Competency Item
                                </th>
                                <th
                                    v-for="s in sessions"
                                    :key="s.id"
                                    class="min-w-16 px-2 py-3 text-center font-medium text-muted-foreground"
                                >
                                    <div class="text-xs font-semibold">S{{ s.number }}</div>
                                    <div class="text-[10px] font-normal opacity-70">{{ s.date }}</div>
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
                                    >
                                        <span
                                            class="inline-flex h-7 w-9 items-center justify-center rounded text-xs font-semibold tabular-nums"
                                            :class="cellClass(cell)"
                                        >
                                            {{ cellLabel(cell) }}
                                        </span>
                                    </td>

                                    <!-- Row average -->
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        <span :class="avgScoreClass(row.avgScore)">
                                            {{ row.avgScore != null ? row.avgScore.toFixed(1) : '—' }}
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
