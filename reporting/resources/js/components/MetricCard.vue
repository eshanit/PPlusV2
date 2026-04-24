<script setup>
import Card from './ui/Card.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    helper: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Card class="p-4">
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
</template>
