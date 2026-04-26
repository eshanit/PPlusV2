<script setup>
import Badge from '../../components/ui/Badge.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, RefreshCw } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    files: { type: Array, default: () => [] },
    downloadUrlTemplate: { type: String, required: true },
    types: { type: Object, default: () => ({}) },
});

function downloadUrl(path) {
    return props.downloadUrlTemplate.replace('__PATH__', path);
}
</script>

<template>
    <Head title="Exports" />

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">Exports</h1>
            <p class="text-sm text-muted-foreground">
                Download generated CSV snapshots. New exports are created daily at 6am + weekly (Mondays).
            </p>
        </div>

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
                                No exports yet. Run the export command manually or wait for the next scheduled run.
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