<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    tooltip: { type: String, required: true },
});
</script>

<template>
    <div class="group relative inline-block">
        <Link
            :href="href"
            class="text-teal-600 underline decoration-dotted decoration-teal-400/60 underline-offset-2 hover:text-teal-700 hover:decoration-teal-600"
        >
            <slot />
        </Link>
        <div class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 min-w-max -translate-x-1/2 translate-y-1 rounded-md border border-teal-300/60 bg-teal-400/20 px-3 py-1.5 text-xs font-semibold text-teal-800 opacity-0 shadow-sm backdrop-blur-sm transition-all duration-150 group-hover:translate-y-0 group-hover:opacity-100">
            {{ tooltip }}
            <div class="absolute -bottom-1 left-1/2 size-2 -translate-x-1/2 rotate-45 border-b border-r border-teal-300/60 bg-teal-400/20 backdrop-blur-sm" />
        </div>
    </div>
</template>
