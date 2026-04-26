<script setup>
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Activity, CheckCircle2, Clock3, Flag } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    summary: { type: Object, required: true },
    byTool: { type: Array, default: () => [] },
    bySupervision: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    domainOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const statusOptions = [
    { value: 'open', label: 'Open only' },
    { value: 'resolved', label: 'Resolved only' },
];

const supervisionColor = (level) => ({
    intensive_mentorship: 'bg-red-500',
    ongoing_mentorship: 'bg-amber-400',
    independent_practice: 'bg-emerald-500',
}[level] ?? 'bg-gray-400');

const supervisionTotal = props.bySupervision.reduce((sum, r) => sum + r.total, 0);

const whole = (n) => new Intl.NumberFormat().format(Number(n ?? 0));
const metric = (n, suffix = '') => (n == null ? '—' : `${Number(n).toFixed(1)}${suffix}`);
</script>

<template>
    <Head title="Gap Overview" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Gap Overview</h1>
            <p class="text-sm text-muted-foreground">Aggregated gap analytics by tool and supervision level.</p>
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
                    key: 'domain',
                    label: 'Domain',
                    placeholder: 'All domains',
                    options: Object.entries(domainOptions).map(([v, l]) => ({ value: v, label: l })),
                },
                {
                    key: 'status',
                    label: 'Resolution',
                    placeholder: 'All',
                    options: statusOptions,
                },
            ]"
        />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Total gaps" :value="whole(summary.total)">
                <template #icon><Flag class="size-5" /></template>
            </MetricCard>
            <MetricCard label="Open gaps" :value="whole(summary.open)">
                <template #icon><Activity class="size-5" /></template>
            </MetricCard>
            <MetricCard label="Resolved" :value="whole(summary.resolved)">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </MetricCard>
            <MetricCard label="Avg days to resolve" :value="metric(summary.avgDaysToResolve, 'd')">
                <template #icon><Clock3 class="size-5" /></template>
            </MetricCard>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1fr_320px]">
            <Card>
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Gaps by Tool</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Tool</th>
                                <th class="px-4 py-3 font-medium text-right">Total</th>
                                <th class="px-4 py-3 font-medium text-right">Open</th>
                                <th class="px-4 py-3 font-medium text-right">Resolved</th>
                                <th class="px-4 py-3 font-medium">% Resolved</th>
                                <th class="px-4 py-3 font-medium text-right">Avg Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="byTool.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    No gaps match the current filters.
                                </td>
                            </tr>
                            <tr
                                v-for="row in byTool"
                                :key="row.toolId"
                                class="border-t hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">{{ row.tool }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ row.total }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span :class="row.open > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">
                                        {{ row.open }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ row.resolved }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 rounded-full bg-muted">
                                            <div
                                                class="h-1.5 rounded-full bg-emerald-500 transition-all"
                                                :style="{ width: `${row.pctResolved}%` }"
                                            />
                                        </div>
                                        <span class="w-10 text-right text-xs tabular-nums text-muted-foreground">
                                            {{ row.pctResolved.toFixed(1) }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                    {{ row.avgDaysToResolve != null ? row.avgDaysToResolve.toFixed(1) + 'd' : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card class="p-4">
                <h2 class="mb-4 text-base font-semibold">Open Gaps by Supervision Level</h2>
                <div v-if="bySupervision.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    No open supervision recommendations.
                </div>
                <div v-else class="space-y-4">
                    <div v-for="row in bySupervision" :key="row.level">
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{ row.label }}</span>
                            <span class="font-semibold">
                                {{ row.total }}
                                <span class="font-normal text-muted-foreground">
                                    ({{ supervisionTotal > 0 ? Math.round(row.total / supervisionTotal * 100) : 0 }}%)
                                </span>
                            </span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted">
                            <div
                                :class="['h-2 rounded-full transition-all', supervisionColor(row.level)]"
                                :style="{ width: supervisionTotal > 0 ? `${row.total / supervisionTotal * 100}%` : '0%' }"
                            />
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </main>
</template>
