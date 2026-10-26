<script setup>
import Card from '../../components/ui/Card.vue';
import FilterBar from '../../components/FilterBar.vue';
import MetricCard from '../../components/MetricCard.vue';
import InsightsPanel from '../../components/InsightsPanel.vue';
import TableLink from '../../components/ui/TableLink.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    byFacility: { type: Array, default: () => [] },
    byItem: { type: Array, default: () => [] },
    details: { type: Array, default: () => [] },
    detailLimit: { type: Number, default: 200 },
    reasons: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const selects = computed(() => [
    { key: 'tool_id', label: 'Tool', placeholder: 'All tools', options: props.tools.map((t) => ({ value: String(t.id), label: t.label })) },
    { key: 'district_id', label: 'District', placeholder: 'All districts', options: props.districts.map((d) => ({ value: String(d.id), label: d.name })) },
    { key: 'facility_id', label: 'Facility', placeholder: 'All facilities', options: props.facilities.map((f) => ({ value: String(f.id), label: f.name })) },
    { key: 'reason', label: 'Reason', placeholder: 'All reasons', options: props.reasons },
]);

const exportUrl = computed(() => `/not-assessable?${new URLSearchParams({ ...props.filters, export: 'csv' }).toString()}`);
const heatmapUrl = (groupId) => `/journey-heatmap?group_id=${encodeURIComponent(groupId)}`;
const formatDate = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
</script>

<template>
    <Head title="Not Assessable" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <InsightsPanel
            summary="Competencies mentors could not assess at a mentee’s facility, by facility and reason. Grouped this way, they point to missing medicines, tests, equipment or services."
            :points="[
                'A mentor marks a competency not assessable, with a reason, when the facility lacks what it needs. It then does not count towards that mentee’s competency until it is scored.',
                'Only marks still in force are counted. Scoring the item later, or clearing the mark in the field app, removes it.',
                'Several mentees at one facility with the same item and reason, for example C11 with Medicine not available, suggests a supply or service gap rather than a training need.',
            ]"
        />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-normal">Not Assessable</h1>
                <p class="text-sm text-muted-foreground">Competencies that cannot be assessed at the mentee’s facility, and why.</p>
            </div>
            <a :href="exportUrl" class="inline-flex h-9 items-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-medium hover:bg-muted">
                <Download class="size-4" />
                Export CSV
            </a>
        </div>

        <FilterBar :filters="filters" :selects="selects" url="/not-assessable" />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard label="Items not assessable" :value="String(summary.items ?? 0)" helper="Marks in force, one per mentee and item" />
            <MetricCard label="Journeys affected" :value="String(summary.journeys ?? 0)" helper="Mentee and tool" />
            <MetricCard label="Mentees" :value="String(summary.mentees ?? 0)" />
            <MetricCard label="Facilities" :value="String(summary.facilities ?? 0)" helper="With at least one mark" />
        </section>

        <!-- By facility and reason -->
        <Card>
            <div class="border-b border-border px-4 py-3">
                <h2 class="text-sm font-semibold">By facility and reason</h2>
                <p class="text-xs text-muted-foreground">Most marks first. Item counts show how many mentees each item affects.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Facility / District</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">Items</th>
                            <th class="px-4 py-3 font-medium text-right">Marks</th>
                            <th class="px-4 py-3 font-medium text-right">Mentees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="byFacility.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No competencies are marked not assessable for these filters.</td>
                        </tr>
                        <tr v-for="row in byFacility" :key="`${row.facility}-${row.reason}`" class="border-t border-border">
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ row.facility }}</span>
                                <span class="block text-xs text-muted-foreground">{{ row.district ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">{{ row.reasonLabel }}</td>
                            <td class="px-4 py-3">
                                <span class="flex flex-wrap gap-1">
                                    <span
                                        v-for="item in row.itemSummary"
                                        :key="`${item.tool}-${item.number}`"
                                        class="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium"
                                        :title="item.tool"
                                    >
                                        {{ item.number }}<template v-if="item.count > 1"> ×{{ item.count }}</template>
                                    </span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ row.items }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.mentees }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Most often not assessable -->
        <Card v-if="byItem.length">
            <div class="border-b border-border px-4 py-3">
                <h2 class="text-sm font-semibold">Competencies most often not assessable</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Competency</th>
                            <th class="px-4 py-3 font-medium">Tool</th>
                            <th class="px-4 py-3 font-medium">Reasons</th>
                            <th class="px-4 py-3 font-medium text-right">Journeys</th>
                            <th class="px-4 py-3 font-medium text-right">Facilities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in byItem" :key="`${item.tool}-${item.number}`" class="border-t border-border">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-muted-foreground">{{ item.number }}</span>
                                {{ item.title }}
                            </td>
                            <td class="px-4 py-3">{{ item.tool }}</td>
                            <td class="px-4 py-3 text-xs">
                                <span v-for="(count, label) in item.reasons" :key="label" class="block">{{ label }}<template v-if="count > 1"> ({{ count }})</template></span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ item.journeys }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ item.facilities }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Every mark -->
        <Card v-if="details.length">
            <div class="border-b border-border px-4 py-3">
                <h2 class="text-sm font-semibold">Every mark</h2>
                <p v-if="summary.items > detailLimit" class="text-xs text-muted-foreground">
                    Showing the {{ detailLimit }} most recent. Export CSV for all {{ summary.items }}.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Mentee</th>
                            <th class="px-4 py-3 font-medium">Competency</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">Facility</th>
                            <th class="px-4 py-3 font-medium">Marked</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in details" :key="`${m.groupId}-${m.itemKey}`" class="border-t border-border align-top">
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ m.mentee }}</span>
                                <span class="block text-xs text-muted-foreground">{{ m.tool }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-muted-foreground">{{ m.number }}</span>
                                {{ m.title }}
                                <span v-if="m.isAdvanced" class="ml-1 rounded bg-muted px-1 text-[0.65rem] uppercase">Advanced</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ m.reasonLabel }}
                                <span v-if="m.note" class="block text-xs text-muted-foreground">{{ m.note }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ m.facility ?? '—' }}
                                <span class="block text-xs text-muted-foreground">{{ m.district ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ formatDate(m.markedAt) }}
                                <span v-if="m.markedBy" class="block text-xs text-muted-foreground">by {{ m.markedBy }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <TableLink :href="heatmapUrl(m.groupId)" tooltip="Open the journey heatmap">Heatmap →</TableLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
