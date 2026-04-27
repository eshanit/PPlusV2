<script setup>
import Card from '../../components/ui/Card.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart2 } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tools: { type: Array, default: () => [] },
    totalScored: { type: Number, default: 0 },
    totalItems: { type: Number, default: 0 },
});

const SCORE_LABELS = ['1', '2', '3', '4', '5'];
const SCORE_KEYS = ['count1', 'count2', 'count3', 'count4', 'count5'];

function countColor(count, max) {
    if (count === 0) return 'bg-gray-50 text-gray-400';
    const ratio = max > 0 ? count / max : 0;
    if (ratio <= 0.05) return 'bg-gray-100 text-gray-700';
    if (ratio <= 0.15) return 'bg-green-100 text-green-800';
    if (ratio <= 0.30) return 'bg-green-300 text-green-900';
    if (ratio <= 0.50) return 'bg-green-500 text-white';
    if (ratio <= 0.75) return 'bg-green-700 text-white';
    return 'bg-green-900 text-white font-semibold';
}

function pctBarWidth(pct) {
    return `${Math.min(100, pct)}%`;
}

function overallMax(row) {
    return Math.max(row.count1, row.count2, row.count3, row.count4, row.count5);
}
</script>

<template>
    <Head title="Score Distribution by Tool" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Score Distribution by Tool</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    How scores 1–5 are distributed per mentorship tool — colour intensity reflects volume.
                </p>
            </div>
            <BarChart2 class="size-8 text-primary" />
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card class="rounded-xl p-4 text-center">
                <div class="text-2xl font-semibold">{{ tools.length }}</div>
                <div class="text-xs text-muted-foreground">Tools</div>
            </Card>
            <Card class="rounded-xl p-4 text-center">
                <div class="text-2xl font-semibold">{{ props.totalScored.toLocaleString() }}</div>
                <div class="text-xs text-muted-foreground">Total scored responses</div>
            </Card>
            <Card class="rounded-xl p-4 text-center">
                <div class="text-2xl font-semibold">
                    {{ tools.length > 0 ? (props.totalScored / props.totalItems).toFixed(1) : '—' }}
                </div>
                <div class="text-xs text-muted-foreground">Avg responses per tool</div>
            </Card>
            <Card class="rounded-xl p-4 text-center">
                <div class="text-2xl font-semibold">
                    {{ tools.length > 0 ? (tools.reduce((s, t) => s + t.avgScore, 0) / tools.length).toFixed(2) : '—' }}
                </div>
                <div class="text-xs text-muted-foreground">Overall avg score</div>
            </Card>
        </div>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 text-center font-medium">Avg Score</th>
                            <th
                                v-for="label in SCORE_LABELS"
                                :key="label"
                                class="px-4 py-3 text-center font-medium"
                            >
                                {{ label }}
                            </th>
                            <th class="px-4 py-3 text-center font-medium">% Goal (4–5)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tools.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
                                No scored responses have been synced yet.
                            </td>
                        </tr>
                        <tr
                            v-for="row in tools"
                            :key="row.toolId"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-medium">
                                <TableLink :href="`/tool-analysis?tool_id=${row.toolId}`">
                                    {{ row.toolLabel }}
                                </TableLink>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums font-medium">
                                {{ row.avgScore !== null ? row.avgScore.toFixed(2) : '—' }}
                            </td>
                            <td
                                v-for="key in SCORE_KEYS"
                                :key="key"
                                class="px-4 py-3 text-center"
                            >
                                <div class="flex flex-col items-center gap-1">
                                    <div
                                        class="flex min-w-16 items-center justify-center rounded px-2 py-1 text-sm tabular-nums"
                                        :class="countColor(row[key], overallMax(row))"
                                    >
                                        {{ row[key] }}
                                    </div>
                                    <div class="h-1 w-16 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-1 rounded-full bg-primary transition-all"
                                            :style="{ width: pctBarWidth(row['pct' + key.replace('count', '')]) }"
                                        />
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="h-2 w-20 rounded-full bg-muted">
                                        <div
                                            class="h-2 rounded-full bg-emerald-500 transition-all"
                                            :style="{ width: pctBarWidth(row.pct4 + row.pct5) }"
                                        />
                                    </div>
                                    <span class="w-14 text-right tabular-nums font-medium text-emerald-600">
                                        {{ (row.pct4 + row.pct5).toFixed(1) }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t px-4 py-3">
                <div class="flex flex-wrap items-center justify-center gap-4 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-gray-100"></span> ≤5%
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-green-100"></span> 6–15%
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-green-300"></span> 16–30%
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-green-500"></span> 31–50%
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-green-700"></span> 51–75%
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-3 w-3 rounded bg-green-900"></span> &gt;75%
                    </span>
                </div>
            </div>
        </Card>
    </main>
</template>