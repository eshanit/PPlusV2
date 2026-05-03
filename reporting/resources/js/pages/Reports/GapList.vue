<script setup>
import FilterBar from '../../components/FilterBar.vue';
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import Pagination from '../../components/ui/Pagination.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    gaps: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    tool: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const statusOptions = [
    { value: 'open', label: 'Open only' },
    { value: 'resolved', label: 'Resolved only' },
];

const domainConfig = {
    knowledge: { label: 'Knowledge', cls: 'bg-blue-100 text-blue-700' },
    critical_reasoning: { label: 'Critical Reasoning', cls: 'bg-purple-100 text-purple-700' },
    clinical_skills: { label: 'Clinical Skills', cls: 'bg-emerald-100 text-emerald-700' },
    communication: { label: 'Communication', cls: 'bg-amber-100 text-amber-700' },
    attitude: { label: 'Attitude', cls: 'bg-rose-100 text-rose-700' },
};
</script>

<template>
    <Head :title="tool ? `Gaps — ${tool.label}` : 'Gap List'" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/gap-overview" class="flex items-center gap-1 hover:text-foreground">
                <ArrowLeft class="size-4" />
                Gap Overview
            </Link>
            <template v-if="tool">
                <span class="opacity-40">/</span>
                <span class="text-foreground">{{ tool.label }}</span>
            </template>
        </div>

        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">
                {{ tool ? `Gaps — ${tool.label}` : 'All Gaps' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Individual gap entries{{ tool ? ` for ${tool.label}` : '' }}. Click View to see the full gap report.
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
                    key: 'status',
                    label: 'Resolution',
                    placeholder: 'All',
                    options: statusOptions,
                },
            ]"
        />

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th v-if="!tool" class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Domains</th>
                            <th class="px-4 py-3 font-medium">Supervision</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="gaps.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
                                No gaps match the current filters.
                            </td>
                        </tr>
                        <tr
                            v-for="gap in gaps"
                            :key="gap.id"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 text-sm tabular-nums text-muted-foreground whitespace-nowrap">
                                {{ gap.identifiedAt }}
                            </td>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">{{ gap.mentee }}</td>
                            <td v-if="!tool" class="px-4 py-3 text-sm text-muted-foreground whitespace-nowrap">
                                {{ gap.tool }}
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="line-clamp-2 text-sm">{{ gap.description }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="d in gap.domains"
                                        :key="d"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="domainConfig[d]?.cls ?? 'bg-muted text-muted-foreground'"
                                    >
                                        {{ domainConfig[d]?.label ?? d }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground whitespace-nowrap">
                                {{ gap.supervisionLabel ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="gap.isResolved ? 'success' : 'warning'">
                                    {{ gap.isResolved ? 'Resolved' : 'Open' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <TableLink
                                    :href="`/gap-report/${gap.id}`"
                                    tooltip="View full gap report"
                                >
                                    View →
                                </TableLink>
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
