<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, MapPin, ShieldAlert } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    alerts: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const scoreBg = (score) => {
    if (score === 1) return 'bg-red-200 text-red-800';
    return 'bg-orange-100 text-orange-800';
};
</script>

<template>
    <Head title="High-Risk Alerts" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-6 text-red-500" />
                <h1 class="text-2xl font-semibold tracking-normal">High-Risk Alerts</h1>
            </div>
            <p class="text-sm text-muted-foreground">
                Mentees whose latest score on a patient-safety-critical competency is 1 or 2. These require urgent mentorship attention.
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
                    key: 'facility_id',
                    label: 'Facility',
                    placeholder: 'All facilities',
                    options: facilities.map((f) => ({ value: String(f.id), label: f.name })),
                },
                {
                    key: 'district_id',
                    label: 'District',
                    placeholder: 'All districts',
                    options: districts.map((d) => ({ value: String(d.id), label: d.name })),
                },
            ]"
        />

        <!-- KPI summary -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard
                label="Active alerts"
                :value="String(summary.total)"
                helper="Mentee × critical item pairs scoring ≤ 2"
            />
            <MetricCard
                label="Score = 1 (critical)"
                :value="String(summary.score1Count)"
                helper="Lowest possible — requires immediate action"
            />
            <MetricCard
                label="Score = 2 (poor)"
                :value="String(summary.score2Count)"
                helper="Significant gap on a high-risk competency"
            />
            <MetricCard
                label="Mentees affected"
                :value="String(summary.affectedMentees)"
                :helper="`Across ${summary.totalCriticalItems} monitored critical items`"
            />
        </section>

        <!-- Empty state -->
        <div
            v-if="alerts.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed border-emerald-300/60 bg-emerald-50/40 py-20 text-center"
        >
            <ShieldAlert class="mb-3 size-10 text-emerald-400" />
            <p class="text-sm font-medium text-emerald-700">No high-risk alerts — all critical items are scoring ≥ 3.</p>
            <p class="mt-1 text-xs text-emerald-600/70">Check filters if you expected results here.</p>
        </div>

        <!-- Alert table -->
        <Card v-else>
            <div class="border-b px-4 py-3">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-4 text-orange-500" />
                    <h2 class="text-base font-semibold">Active Alerts</h2>
                </div>
                <p class="text-xs text-muted-foreground">
                    Sorted by severity (score 1 first), then by date last scored (oldest first = most overdue for re-assessment).
                    Critical items are those with direct patient-safety implications — emergency management, hospitalization decisions, life-threatening complications.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium">Critical Item</th>
                            <th class="px-4 py-3 font-medium text-right">Latest Score</th>
                            <th class="px-4 py-3 font-medium text-right">Last Scored</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(alert, idx) in alerts"
                            :key="idx"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ alert.mentee }}</p>
                                <p v-if="alert.facility" class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <MapPin class="size-3 opacity-60" />
                                    {{ alert.facility }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ alert.tool }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <div class="flex items-start gap-1.5">
                                    <span class="shrink-0 font-mono text-xs text-muted-foreground">
                                        {{ alert.itemNumber }}
                                    </span>
                                    <div>
                                        <p class="line-clamp-2 leading-snug">{{ alert.itemTitle }}</p>
                                        <p class="mt-0.5 text-[10px] text-muted-foreground">{{ alert.category }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span
                                    class="inline-block min-w-8 rounded-full px-2 py-0.5 text-center text-xs font-bold tabular-nums"
                                    :class="scoreBg(alert.latestScore)"
                                >
                                    {{ alert.latestScore }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ alert.scoreDate ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <TableLink
                                    :href="`/journey-sessions?group_id=${encodeURIComponent(alert.evaluationGroupId)}`"
                                    tooltip="View all sessions for this journey"
                                >
                                    Sessions →
                                </TableLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Info callout -->
        <div class="rounded-lg border border-orange-200/60 bg-orange-50/50 p-4 text-sm text-orange-800">
            <p class="font-medium">About high-risk items</p>
            <p class="mt-1 text-orange-700/80">
                {{ summary.totalCriticalItems }} competency items are currently flagged as high-risk — those involving emergency management,
                hospitalization decisions, acute complication recognition, or life-threatening clinical scenarios.
                The clinical team should review and confirm this list before go-live.
                Items can be managed via the admin panel.
            </p>
        </div>
    </main>
</template>
