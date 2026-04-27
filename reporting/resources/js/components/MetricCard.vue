<script setup>
import Card from './ui/Card.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    helper: { type: String, default: '' },
    href: { type: String, default: null },
});
</script>

<template>
    <component :is="href ? Link : 'div'" :href="href ?? undefined" class="group block">
        <Card
            class="p-4 transition-colors"
            :class="href ? 'cursor-pointer group-hover:border-primary/30 group-hover:bg-muted/30' : ''"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-normal">{{ value }}</p>
                </div>
                <div class="rounded-md bg-secondary p-2 text-primary">
                    <slot name="icon" />
                </div>
            </div>
            <p v-if="helper" class="mt-3 text-xs text-muted-foreground">{{ helper }}</p>
        </Card>
    </component>
</template>
