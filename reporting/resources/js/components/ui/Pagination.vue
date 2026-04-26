<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    meta: {
        type: Object,
        required: true,
    },
});

function go(url) {
    if (!url) return;
    router.visit(url, { preserveScroll: false });
}
</script>

<template>
    <div class="flex items-center justify-between text-sm text-muted-foreground">
        <span>
            Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }} results
        </span>
        <div class="flex items-center gap-1">
            <button
                v-for="link in meta.links"
                :key="link.label"
                :disabled="!link.url"
                :class="[
                    'min-w-8 rounded-md border px-2 py-1 text-xs font-medium transition-colors',
                    link.active
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card hover:bg-secondary disabled:cursor-default disabled:opacity-40',
                ]"
                @click="go(link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
