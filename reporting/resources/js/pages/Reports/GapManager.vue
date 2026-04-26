<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Button from '../../components/ui/Button.vue';
import Card from '../../components/ui/Card.vue';
import Badge from '../../components/ui/Badge.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Search, Edit, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    domainOptions: { type: Object, required: true },
    supervisionOptions: { type: Object, required: true },
    tools: { type: Array, default: () => [] },
});

const query = ref('');
const results = ref([]);
const loading = ref(false);

async function search() {
    if (query.value.length < 2) {
        results.value = [];
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(`/gaps/search?q=${encodeURIComponent(query.value)}`);
        results.value = await response.json();
    } catch (e) {
        results.value = [];
    } finally {
        loading.value = false;
    }
}

const goToEdit = (id) => {
    window.location.href = `/gaps/${id}`;
};
</script>

<template>
    <Head title="Gap Manager" />

    <main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-normal">Gap Manager</h1>
                <p class="text-sm text-muted-foreground">
                    Search and manage individual gaps. Admin access required.
                </p>
            </div>
            <Button as="a" href="/gap-overview" variant="outline">
                Back to Overview
            </Button>
        </div>

        <Card class="p-6">
            <div class="space-y-4">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Search by gap ID, mentee name, or description..."
                        class="w-full rounded-md border border-input bg-background pl-10 px-4 py-2 text-sm"
                        @input="search"
                    />
                </div>

                <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">
                    Searching...
                </div>

                <div v-else-if="results.length === 0 && query.length >= 2" class="py-8 text-center text-sm text-muted-foreground">
                    No gaps found matching "{{ query }}"
                </div>

                <div v-else-if="results.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Gap ID</th>
                                <th class="px-4 py-3 font-medium">Mentee</th>
                                <th class="px-4 py-3 font-medium">Tool</th>
                                <th class="px-4 py-3 font-medium">Identified</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="gap in results" :key="gap.id" class="border-t hover:bg-muted/30">
                                <td class="px-4 py-3 font-mono text-xs">{{ gap.id.slice(0, 8) }}...</td>
                                <td class="px-4 py-3">{{ gap.mentee }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ gap.tool }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ gap.identifiedAt }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="gap.isResolved ? 'success' : 'warning'">
                                        {{ gap.isResolved ? 'Resolved' : 'Open' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button size="sm" variant="ghost" @click="goToEdit(gap.id)">
                                        <Edit class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                    Start typing to search gaps. Results will appear here.
                </div>
            </div>
        </Card>
    </main>
</template>