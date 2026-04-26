<script setup>
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    selects: {
        // [{ key, label, options: [{ value, label }], placeholder }]
        type: Array,
        default: () => [],
    },
    url: { type: String, default: () => window.location.pathname },
});

const local = ref({ ...props.filters });

watch(
    local,
    (val) => {
        const params = Object.fromEntries(
            Object.entries(val).filter(([, v]) => v !== '' && v != null),
        );
        router.get(props.url, params, { preserveState: true, preserveScroll: false, replace: true });
    },
    { deep: true },
);
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <div v-for="select in selects" :key="select.key" class="flex flex-col gap-1">
            <label class="text-xs font-medium text-muted-foreground">{{ select.label }}</label>
            <select
                v-model="local[select.key]"
                class="h-8 rounded-md border border-border bg-card px-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
            >
                <option value="">{{ select.placeholder ?? 'All' }}</option>
                <option v-for="opt in select.options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>
        <slot />
    </div>
</template>
