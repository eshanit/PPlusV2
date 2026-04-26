<script setup>
import FilterBar from '../../components/FilterBar.vue';
import Card from '../../components/ui/Card.vue';
import Pagination from '../../components/ui/Pagination.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'avg_score' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'avg_score', label: 'Avg Score', sortable: true, align: 'right' },
    { key: 'pct_at_goal', label: '% At Goal', sortable: true, align: 'right' },
    { key: 'journeys_below_4', label: 'Below Goal', sortable: true, align: 'right' },
    { key: 'total_journeys', label: 'Total Journeys', sortable: true, align: 'right' },
];

function sortBy(key) {
    const newDirection = props.sort === key && props.direction === 'asc' ? 'desc' : 'asc';
    router.get(
        '/low-score-watchlist',
        { ...props.filters, sort: key, direction: newDirection },
        { preserveState: true, preserveScroll: false, replace: true },
    );
}

const scoreColor = (score) => {
    if (score >= 4) return 'text-emerald-600 font-semibold';
    if (score >= 3) return 'text-amber-600 font-semibold';
    return 'text-red-600 font-semibold';
};

const goalBarWidth = (pct) => `${Math.min(100, Math.max(0, pct))}%`;
</script>

<template>
    <Head title="Low-Score Watchlist" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Low-Score Watchlist</h1>
            <p class="text-sm text-muted-foreground">
                Carry-forward scores per competency item across all journeys — sorted weakest first.
                Items below 3.0 average need urgent attention.
            </p>
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
            ]"
        />

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="w-14 px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Competency</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-3 font-medium"
                                :class="[col.align === 'right' ? 'text-right' : '', col.sortable ? 'cursor-pointer select-none hover:text-foreground' : '']"
                                @click="col.sortable ? sortBy(col.key) : null"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ col.label }}
                                    <component
                                        :is="sort === col.key ? (direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                        :class="['size-3', sort === col.key ? 'opacity-100' : 'opacity-30']"
                                    />
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                No items match the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 text-xs font-mono text-muted-foreground">{{ item.number }}</td>
                            <td class="px-4 py-3 font-medium leading-snug">{{ item.title }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ item.tool }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="h-1.5 w-20 rounded-full bg-muted">
                                        <div
                                            class="h-1.5 rounded-full bg-primary transition-all"
                                            :style="{ width: `${(item.avgScore / 5) * 100}%` }"
                                        />
                                    </div>
                                    <span :class="scoreColor(item.avgScore)" class="w-8 text-right tabular-nums">
                                        {{ item.avgScore.toFixed(2) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="h-1.5 w-16 rounded-full bg-muted">
                                        <div
                                            class="h-1.5 rounded-full bg-emerald-500 transition-all"
                                            :style="{ width: goalBarWidth(item.pctAtGoal) }"
                                        />
                                    </div>
                                    <span class="w-12 text-right tabular-nums text-muted-foreground">
                                        {{ item.pctAtGoal.toFixed(1) }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="item.journeysBelow4 > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">
                                    {{ item.journeysBelow4 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ item.totalJourneys }}</td>
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
