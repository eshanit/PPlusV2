<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const forwardedAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});
</script>

<template>
    <section
        v-bind="forwardedAttrs"
        :class="[
            'rounded-lg border bg-card text-card-foreground shadow-sm',
            attrs.class,
        ]"
    >
        <slot />
    </section>
</template>
