<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
});

const attrs = useAttrs();

const variants = {
    default: 'border-transparent bg-primary text-primary-foreground',
    secondary: 'border-transparent bg-secondary text-secondary-foreground',
    outline: 'text-foreground',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
};

const forwardedAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});

const classes = computed(() => [
    'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
    variants[props.variant] ?? variants.default,
    attrs.class,
]);
</script>

<template>
    <span v-bind="forwardedAttrs" :class="classes">
        <slot />
    </span>
</template>
