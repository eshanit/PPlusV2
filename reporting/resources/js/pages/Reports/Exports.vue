<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import InsightsPanel from '../../components/InsightsPanel.vue';
import Button from '../../components/ui/Button.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, RefreshCw } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const page = usePage();
const form = useForm({});
const scoresCsvForm = useForm({});

const props = defineProps({
    files: { type: Array, default: () => [] },
    downloadUrlTemplate: { type: String, required: true },
    types: { type: Object, default: () => ({}) },
});

function downloadUrl(path) {
    return props.downloadUrlTemplate.replace('__PATH__', path);
}

function generateReports() {
    form.post('/exports/generate');
}

function generateScoresCsv() {
    scoresCsvForm.post('/exports/generate-mentee-scores-csv');
}
</script>

<template>
    <Head title="Exports" />

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <InsightsPanel
            summary="Generate point-in-time report files from the data currently synchronized into reporting."
            :points="[
                'Generate reports now refreshes the available report outputs using the current reporting database; it does not trigger a CouchDB sync.',
                'The mentee-scores CSV is a flat, spreadsheet-friendly extract with one row per competency item per session; it is generally faster to open and process than XLSX.',
                'The XLSX export is convenient for Excel users but may take longer for large datasets. Both contain the same score-level information.',
                'Exports are snapshots. Run generation again after data syncs or report definitions change to create a fresh file.',
            ]"
        />
        <div class="flex flex-col gap-1">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl font-semibold tracking-normal">Exports</h1>
                    <p class="text-sm text-muted-foreground">
                        Download CSV reports or an Excel workbook. Scheduled exports run daily at 6am and weekly on Mondays.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing || scoresCsvForm.processing" @click="generateReports">
                        <RefreshCw class="size-4" :class="{ 'animate-spin': form.processing }" />
                        {{ form.processing ? 'Generating...' : 'Generate all reports' }}
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="form.processing || scoresCsvForm.processing"
                        @click="generateScoresCsv"
                    >
                        <RefreshCw class="size-4" :class="{ 'animate-spin': scoresCsvForm.processing }" />
                        {{ scoresCsvForm.processing ? 'Generating CSV...' : 'Generate scores CSV' }}
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="page.props.flash?.success"
            role="status"
            class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="form.errors.generation"
            role="alert"
            class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ form.errors.generation }}
        </div>
        <p class="text-xs text-muted-foreground">
            Mentee Scores CSV and Excel exports list each tool competency score by session, including session number and recorded rounds. CSV is faster for large datasets; counselling scores are excluded.
        </p>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">File</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Size</th>
                            <th class="px-4 py-3 font-medium">Generated</th>
                            <th class="px-4 py-3 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="files.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                No exports yet. Generate reports now or wait for the next scheduled run.
                            </td>
                        </tr>
                        <tr
                            v-for="file in files"
                            :key="file.path"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <FileSpreadsheet class="size-4 shrink-0 text-emerald-600" />
                                    <span class="font-medium">{{ file.filename }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{ file.typeLabel }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ file.size }}</td>
                            <td class="px-4 py-3 text-muted-foreground" :title="file.generatedAt">
                                {{ file.generatedAtRelative }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    :href="downloadUrl(file.path)"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    <Download class="size-3.5" />
                                    Download
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>