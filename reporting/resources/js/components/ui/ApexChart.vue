<script setup>
import VueApexCharts from 'vue3-apexcharts';
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    series: { type: Array, required: true },
    options: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 320 },
});

const mergedOptions = computed(() => ({
    chart: {
        toolbar: { show: false },
        fontFamily: 'inherit',
        background: 'transparent',
        ...props.options.chart,
    },
    colors: ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'],
    grid: { borderColor: '#e2e8f0', strokeDashArray: 3, ...props.options.grid },
    tooltip: { theme: 'light', ...props.options.tooltip },
    ...props.options,
    chart: {
        toolbar: { show: false },
        fontFamily: 'inherit',
        background: 'transparent',
        ...props.options.chart,
    },
}));
</script>

<template>
    <VueApexCharts :type="type" :height="height" :series="series" :options="mergedOptions" />
</template>
