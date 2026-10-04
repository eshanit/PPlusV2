<script setup>
import { Lightbulb } from 'lucide-vue-next';

defineProps({
    summary: { type: String, required: true },
    points: { type: Array, default: () => [] },
});
</script>

<template>
    <details class="group rounded-lg border border-sky-200 bg-sky-50/60 text-sky-950">
        <summary class="flex cursor-pointer list-none items-start gap-2.5 px-4 py-3 [&::-webkit-details-marker]:hidden">
            <Lightbulb class="mt-0.5 size-4 shrink-0 text-sky-700" />
            <span class="flex-1">
                <span class="block text-sm font-semibold">Insights — what this report means and how to use it</span>
                <span class="mt-0.5 block text-xs text-sky-900/75">{{ summary }}</span>
            </span>
            <span class="text-xs font-medium text-sky-700 group-open:hidden">Show</span>
            <span class="hidden text-xs font-medium text-sky-700 group-open:inline">Hide</span>
        </summary>
        <div class="border-t border-sky-200 px-4 py-3">
            <ul class="ml-5 list-disc space-y-1.5 text-xs leading-relaxed text-sky-950/85">
                <li v-for="(point, index) in points" :key="index">{{ point }}</li>
            </ul>
        </div>
    </details>
</template>
