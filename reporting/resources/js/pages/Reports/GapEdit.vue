<script setup>
import { Head, router } from '@inertiajs/vue3';
import Button from '../../components/ui/Button.vue';
import Card from '../../components/ui/Card.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Pencil, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    gap: { type: Object, required: true },
    domainOptions: { type: Object, required: true },
    supervisionOptions: { type: Object, required: true },
    tools: { type: Array, default: () => [] },
});

const form = ref({
    description: props.gap.description,
    domains: props.gap.domains || [],
    covered_in_mentorship: props.gap.coveredInMentorship || false,
    covering_later: props.gap.coveringLater || false,
    timeline: props.gap.timeline || '',
    supervision_level: props.gap.supervisionLevel || null,
    resolution_note: props.gap.resolutionNote || '',
    resolved_at: props.gap.resolvedAt || '',
});

const saving = ref(false);

function save() {
    saving.value = true;
    router.put(route('reports.gaps.update', { id: props.gap.id }), form.value, {
        onFinish: () => saving.value = false,
    });
}

function toggleDomain(domain) {
    const idx = form.value.domains.indexOf(domain);
    if (idx >= 0) {
        form.value.domains.splice(idx, 1);
    } else {
        form.value.domains.push(domain);
    }
}

const isResolved = () => !!form.value.resolved_at;
</script>

<template>
    <Head title="Edit Gap" />

    <main class="mx-auto max-w-2xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-normal">Edit Gap</h1>
                <p class="text-sm text-muted-foreground">Update gap details</p>
            </div>
            <div class="flex gap-2">
                <Button as="a" href="/gap-overview" variant="ghost">
                    <X class="size-4 mr-1" />
                    Cancel
                </Button>
                <Button @click="save" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>

        <Card class="p-6 space-y-6">
            <div class="space-y-4">
                <div class="font-mono text-xs text-muted-foreground">
                    ID: {{ gap.id }}
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">Description *</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-ring focus:outline-none focus:ring-2"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Domains *</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(label, key) in domainOptions"
                            :key="key"
                            type="button"
                            @click="toggleDomain(key)"
                            :class="[
                                'rounded-full border px-3 py-1 text-sm transition-colors',
                                form.domains.includes(key)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border hover:border-primary'
                            ]"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <label class="flex items-center gap-2">
                        <input v-model="form.covered_in_mentorship" type="checkbox" class="h-4 w-4 rounded" />
                        <span class="text-sm">Covered in Mentorship</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.covering_later" type="checkbox" class="h-4 w-4 rounded" />
                        <span class="text-sm">Covering Later</span>
                    </label>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">Timeline</label>
                    <input v-model="form.timeline" type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="e.g., Next session" />
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">Supervision Level</label>
                    <select v-model="form.supervision_level" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                        <option :value="null">— Select —</option>
                        <option v-for="(label, key) in supervisionOptions" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">Resolution Note</label>
                    <textarea v-model="form.resolution_note" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="How was this gap resolved?" />
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium">Resolved At</label>
                    <input v-model="form.resolved_at" type="date" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                </div>
            </div>
        </Card>

        <div class="flex justify-between text-xs text-muted-foreground">
            <div>Created: {{ gap.createdAt }}</div>
            <div>Updated: {{ gap.updatedAt }}</div>
        </div>
    </main>
</template>