<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    as: {
        type: [String, Object],
        default: 'button',
    },
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'default',
    },
    size: {
        type: String,
        default: 'default',
    },
});

const attrs = useAttrs();

const variants = {
    default: 'bg-primary text-primary-foreground hover:bg-primary/90',
    outline: 'border border-input bg-background hover:bg-muted',
    ghost: 'hover:bg-muted',
};

const sizes = {
    default: 'h-10 px-4 py-2',
    sm: 'h-8 px-3 text-xs',
    icon: 'h-9 w-9',
};

const forwardedAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    'disabled:pointer-events-none disabled:opacity-50',
    variants[props.variant] ?? variants.default,
    sizes[props.size] ?? sizes.default,
    attrs.class,
]);
</script>

<template>
    <component
        :is="as"
        v-bind="forwardedAttrs"
        :type="as === 'button' ? type : undefined"
        :class="classes"
    >
        <slot />
    </component>
</template>
