<template>
  <div class="flex gap-1">
    <button
      v-for="s in SCORES"
      :key="s.label"
      class="w-8 h-8 rounded text-xs font-semibold transition-colors"
      :class="modelValue === s.value ? s.activeClass : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'"
      @click="$emit('update:modelValue', s.value)"
    >
      {{ s.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{ modelValue: number | null }>()
defineEmits<{ 'update:modelValue': [value: number | null] }>()

const SCORES = [
  { value: 1,    label: '1',   activeClass: 'bg-red-500 text-white' },
  { value: 2,    label: '2',   activeClass: 'bg-orange-500 text-white' },
  { value: 3,    label: '3',   activeClass: 'bg-amber-500 text-white' },
  { value: 4,    label: '4',   activeClass: 'bg-emerald-500 text-white' },
  { value: 5,    label: '5',   activeClass: 'bg-green-600 text-white' },
  { value: null, label: 'N/A', activeClass: 'bg-gray-500 text-white' },
] as const
</script>
